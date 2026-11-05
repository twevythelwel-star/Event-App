import React, { useEffect, useMemo, useState } from "react";
import {
  CulturalCatalogCategory,
  fetchCulturalCatalog,
  saveCulturalCatalogItem,
  deactivateCulturalCatalogItem,
  CulturalCatalogAdminType,
} from "../lib/api";

type CatalogRecord = {
  type: CulturalCatalogAdminType;
  id: string;
  name: string;
  parentId?: string;
  parentIds?: string[];
  flag?: string | null;
  imageUrl?: string | null;
  alternativeSpellings?: string[];
  isFusion?: boolean;
  dietaryType?: "any" | "vegetarian" | "vegan";
};

const TYPES: { id: CulturalCatalogAdminType; label: string }[] = [
  { id: "categories", label: "Cultural identity" },
  { id: "regions", label: "Region" },
  { id: "countries", label: "Country" },
  { id: "cuisines", label: "Cuisine" },
  { id: "dishes", label: "Dish" },
];

function flattenCatalog(categories: CulturalCatalogCategory[]): CatalogRecord[] {
  const rows: CatalogRecord[] = [];
  const countryRows = new Map<string, CatalogRecord>();
  for (const category of categories) {
    rows.push({ type: "categories", id: category.id, name: category.name });
    for (const region of category.regions) {
      rows.push({ type: "regions", id: region.id, name: region.name, parentId: category.id });
      for (const country of region.countries) {
        const existing = countryRows.get(country.id);
        countryRows.set(country.id, {
          type: "countries",
          id: country.id,
          name: country.name,
          parentIds: [...(existing?.parentIds || []), region.id],
          flag: country.flag,
          imageUrl: country.imageUrl,
        });
        for (const cuisine of country.cuisines) {
          rows.push({
            type: "cuisines",
            id: cuisine.id,
            name: cuisine.name,
            parentId: country.id,
          });
          for (const dish of cuisine.dishes) {
            rows.push({
              type: "dishes",
              id: dish.id,
              name: dish.name,
              parentId: cuisine.id,
              alternativeSpellings: dish.alternativeSpellings,
              isFusion: dish.isFusion,
              dietaryType: dish.dietaryType,
            });
          }
        }
      }
    }
  }
  return [...rows, ...countryRows.values()];
}

export function CulturalCatalogAdminSection() {
  const [catalog, setCatalog] = useState<CulturalCatalogCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [entityType, setEntityType] = useState<CulturalCatalogAdminType>("categories");
  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [stableId, setStableId] = useState("");
  const [parentId, setParentId] = useState("");
  const [regionIds, setRegionIds] = useState<string[]>([]);
  const [flag, setFlag] = useState("");
  const [imageUrl, setImageUrl] = useState("");
  const [spellings, setSpellings] = useState("");
  const [isFusion, setIsFusion] = useState(false);
  const [dietaryType, setDietaryType] = useState<"any" | "vegetarian" | "vegan">("any");
  const [search, setSearch] = useState("");

  const records = useMemo(() => flattenCatalog(catalog), [catalog]);
  const parentOptions = useMemo(() => {
    if (entityType === "regions") return records.filter((row) => row.type === "categories");
    if (entityType === "countries") return records.filter((row) => row.type === "regions");
    if (entityType === "cuisines") return records.filter((row) => row.type === "countries");
    if (entityType === "dishes") return records.filter((row) => row.type === "cuisines");
    return [];
  }, [entityType, records]);
  const filteredRecords = records.filter((row) =>
    `${row.name} ${row.id}`.toLowerCase().includes(search.trim().toLowerCase())
  ).slice(0, 40);

  async function reload() {
    setLoading(true);
    setError(null);
    try {
      setCatalog(await fetchCulturalCatalog());
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "The cultural catalog could not be loaded.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void reload(); }, []);

  function resetForm(nextType = entityType) {
    setEditingId(null);
    setName("");
    setStableId("");
    setParentId(parentOptions[0]?.id || "");
    setRegionIds([]);
    setFlag("");
    setImageUrl("");
    setSpellings("");
    setIsFusion(false);
    setDietaryType("any");
    setEntityType(nextType);
  }

  function editRecord(record: CatalogRecord) {
    setEntityType(record.type);
    setEditingId(record.id);
    setName(record.name);
    setStableId(record.type === "countries" ? record.id : "");
    setParentId(record.parentId || "");
    setRegionIds(record.parentIds || []);
    setFlag(record.flag || "");
    setImageUrl(record.imageUrl || "");
    setSpellings((record.alternativeSpellings || []).join(", "));
    setIsFusion(record.isFusion || false);
    setDietaryType(record.dietaryType || "any");
    setError(null);
    setNotice(null);
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setNotice(null);
    const payload: Record<string, unknown> = { name };
    if (stableId && !editingId) payload.id = stableId;
    if (entityType === "regions") payload.cultural_category_id = parentId;
    if (entityType === "countries") {
      if (!editingId) payload.id = stableId.toUpperCase();
      payload.flag_emoji = flag || null;
      payload.cuisine_image_url = imageUrl || null;
      payload.region_ids = regionIds;
    }
    if (entityType === "cuisines") payload.country_id = parentId;
    if (entityType === "dishes") {
      payload.cuisine_id = parentId;
      payload.alternative_spellings = spellings.split(",").map((value) => value.trim()).filter(Boolean);
      payload.is_fusion = isFusion;
      payload.dietary_type = dietaryType;
    }
    try {
      await saveCulturalCatalogItem(entityType, editingId, payload);
      setNotice(`${TYPES.find((type) => type.id === entityType)?.label} saved.`);
      resetForm();
      await reload();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "The catalog item could not be saved.");
    }
  }

  async function deactivate(record: CatalogRecord) {
    setError(null);
    setNotice(null);
    try {
      await deactivateCulturalCatalogItem(record.type, record.id);
      setNotice(`${record.name} is no longer offered in the active catalog.`);
      if (editingId === record.id) resetForm();
      await reload();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "The catalog item could not be deactivated.");
    }
  }

  return (
    <div className="sys-panel admin-catalog-section">
      <div className="panel-toolbar">
        <div>
          <h3>Cultural identities, regions, cuisines & dishes</h3>
          <p>Maintain the shared discovery catalog. Deactivation preserves existing event selections.</p>
        </div>
      </div>
      {error && <div role="alert" className="system-toast">{error}</div>}
      {notice && <div role="status" className="system-toast">{notice}</div>}

      <form className="admin-catalog-form" onSubmit={(event) => void submit(event)}>
        <label>
          Catalog level
          <select value={entityType} onChange={(event) => resetForm(event.target.value as CulturalCatalogAdminType)}>
            {TYPES.map((type) => <option key={type.id} value={type.id}>{type.label}</option>)}
          </select>
        </label>
        <label>
          Name
          <input required minLength={2} maxLength={160} value={name} onChange={(event) => setName(event.target.value)} />
        </label>
        {entityType !== "dishes" && entityType !== "countries" && (
          <label>
            Stable ID (optional for new items)
            <input maxLength={120} value={stableId} onChange={(event) => setStableId(event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, "-"))} disabled={Boolean(editingId)} />
          </label>
        )}
        {entityType === "countries" && (
          <>
            <label>
              ISO country code
              <input required minLength={2} maxLength={2} value={stableId} onChange={(event) => setStableId(event.target.value.toUpperCase())} disabled={Boolean(editingId)} />
            </label>
            <label>
              Flag (optional)
              <input maxLength={8} value={flag} onChange={(event) => setFlag(event.target.value)} />
            </label>
            <label>
              Cuisine image URL (optional)
              <input type="url" maxLength={500} value={imageUrl} onChange={(event) => setImageUrl(event.target.value)} />
            </label>
            <label>
              Regions (select one or more)
              <select multiple size={4} value={regionIds} onChange={(event) => setRegionIds(Array.from(event.target.selectedOptions, (option) => option.value))}>
                {parentOptions.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
              </select>
            </label>
          </>
        )}
        {["regions", "cuisines", "dishes"].includes(entityType) && (
          <label>
            {entityType === "regions" ? "Cultural identity" : entityType === "cuisines" ? "Country" : "Cuisine"}
            <select required value={parentId} onChange={(event) => setParentId(event.target.value)}>
              <option value="">Select…</option>
              {parentOptions.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
            </select>
          </label>
        )}
        {entityType === "dishes" && (
          <>
            <label>
              Alternative spellings (comma-separated)
              <input value={spellings} onChange={(event) => setSpellings(event.target.value)} />
            </label>
            <label>
              Dietary identity
              <select value={dietaryType} onChange={(event) => setDietaryType(event.target.value as typeof dietaryType)}>
                <option value="any">Not specified</option>
                <option value="vegetarian">Vegetarian</option>
                <option value="vegan">Vegan</option>
              </select>
            </label>
            <label className="admin-catalog-check">
              <input type="checkbox" checked={isFusion} onChange={(event) => setIsFusion(event.target.checked)} />
              Fusion dish
            </label>
          </>
        )}
        <div className="admin-catalog-actions">
          <button type="submit" className="primary-button">{editingId ? "Update item" : "Add item"}</button>
          {editingId && <button type="button" className="secondary-button" onClick={() => resetForm()}>Cancel edit</button>}
        </div>
      </form>

      <div className="panel-toolbar">
        <label>
          Search catalog
          <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name or stable ID" />
        </label>
        <span>{records.length} active entries</span>
      </div>
      {loading ? <p>Loading catalog…</p> : (
        <div className="admin-catalog-list">
          {filteredRecords.map((record) => (
            <div className="admin-catalog-row" key={`${record.type}-${record.id}`}>
              <span><strong>{record.name}</strong><small>{TYPES.find((type) => type.id === record.type)?.label} · {record.id}</small></span>
              <div>
                <button type="button" className="secondary-button" onClick={() => editRecord(record)}>Edit</button>
                <button type="button" className="secondary-button" onClick={() => void deactivate(record)}>Deactivate</button>
              </div>
            </div>
          ))}
          {filteredRecords.length === 0 && <p>No matching catalog entries.</p>}
        </div>
      )}
    </div>
  );
}
