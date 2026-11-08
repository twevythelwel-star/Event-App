# US state and local compliance database

**What it holds:** the food and alcohol authorities and requirements that apply in the United States, every state, the District of Columbia, every county and every city or municipality. Compliance administrators research, review and approve each record.

**Where administrators work:** Admin console → **Compliance Database** (`/admin/console/compliance`).

**How it's built:**
- Schema: `2026_11_07_000058_create_compliance_jurisdiction_database`.
- Models: `App\Models\Compliance\*`.
- Workflow: `App\Services\Compliance\ComplianceReviewWorkflow`.
- Starting data: `Database\Seeders\ComplianceDatabaseSeeder`.

## Ground rules

These rules are enforced in code and in the screens, not just stated:

1. **Nothing is law until it is verified.** Every requirement starts as **Verification Required**. While a record is unverified, the console shows that badge and never shows the record's text as a fact. The seed marks nothing as verified.
2. **Official government sources only.**
   - A record cannot be submitted without an official source link and the date the source was checked.
   - Links must be https on a `.gov`, `.mil` or `.us` site. Any other link needs the administrator to confirm it is the agency's own official site.
   - A record cannot be submitted from a blog or third-party article.
3. **No state's rules are assumed for another, and none for its localities.** Each jurisdiction has its own records. A county or municipality starts with none, and an administrator opens research on it.
4. **No single office stands in for the regulators.** Records are per agency, for example a health department, an agriculture department, an alcohol control board or a revenue department. A governor's office is not treated as the authority for food or alcohol.
5. **Two people per approval.** A system administrator approves a record, and never one they submitted themselves (`compliance.database.require_second_reviewer`).

## Hierarchy

`Country → State → County → City/Municipality`, stored in `compliance_jurisdictions`. Each row has a `parent_id`, a `type` and a `code`.

| Level | Where it comes from | Code |
|---|---|---|
| United States | Seed | `US` |
| 50 states and D.C. | Seed, from the app's state list (territories are left out for now) | State code |
| Counties and county-equivalents | U.S. Census Bureau 2020 county FIPS file | 5-digit FIPS |
| Cities and municipalities (incorporated places) | U.S. Census Bureau 2020 place FIPS file | State FIPS + place FIPS |

**Importing counties and municipalities.** These come from the Census Bureau's official files:

```bash
php artisan compliance:import-census --download
# or, with files already saved:
php artisan compliance:import-census --counties=national_county2020.txt --places=national_place2020.txt
```

How the import behaves:
- Only incorporated places become municipalities. Census-designated places are statistical areas, not governments.
- A municipality's parent is the first county the Census file lists for it.
- Running the import again adds only what is missing.

**Known gaps to research.** The import doesn't cover these yet:
- **Towns and townships that are county subdivisions.** These are not "places", for example most New England towns. They are in the Census county subdivision file.
- **Connecticut planning regions.** Since 2022 these have replaced counties for Census purposes, but the 2020 file still lists the old counties.

## What each record holds

**Jurisdiction:** name, type, parent jurisdiction.

**Authority** (`compliance_authorities`):
- the official agency name;
- its area: food, agriculture, alcohol, revenue or general;
- its role;
- the agency website;
- an official legislation or regulation link and title.

**Requirement** (`compliance_requirements`): one record per jurisdiction per topic. The topics are:
- food permits;
- food handler training;
- food establishments;
- temporary food events;
- alcohol licensing;
- special event permits;
- minimum legal drinking age;
- restrictions and conditions;
- required documentation.

Each requirement holds the summary, a short value (for example the minimum age), conditions, required documents, the citation and the authority it cites. That authority must belong to the jurisdiction or one above it.

**Review fields on both kinds of record:**
- source link and title, and the "confirmed official" flag;
- effective date, last verified date and next review date;
- verification status;
- research notes and compliance administrator notes;
- who submitted it and who approved it, and when.

**History** (`compliance_record_reviews`): every creation, edit, submission, approval, return, reset and review-due flag. Each entry records who acted, both statuses, the comment and a snapshot of the record.

## Administrator workflow

```
Verification Required ─edit→ Draft ─submit→ Pending review ─approve→ Verified ─(review date passes)→ Review due
                               ↑                 │                       │                              │
                               └─ send back ─────┘ (Changes requested)   └─ edit → Draft                └─ resubmit
```

| Step | Who | What it needs |
|---|---|---|
| Open research on a jurisdiction | Any administrator | Creates the 9 requirement records as Verification Required. |
| Add an authority, or edit a record | Any administrator | Saved as a draft. Editing what a verified record says, or its source or effective date, returns it to draft. |
| Submit for review | Any administrator | **Requirement:** a summary. **Authority:** a website. **Both:** an official source link, and a checked date that is not in the future. |
| Approve | A **system administrator other than the submitter** | Sets the next review date (12 months by default, `compliance.database.review_interval_months`). |
| Send back | A system administrator | A comment saying what to change. |
| Mark Verification Required | Any administrator | A reason. This withdraws the record's verification. |
| Review due | Scheduled daily: `compliance:flag-due-reviews` at 03:15 | Verified records past their review date must be checked and approved again. |

## Starting data (initial research, 7 November 2026)

All of it was read from official federal sources and loaded for administrator review. **None of it is marked verified.**

| What | Source | Loaded as |
|---|---|---|
| FDA, USDA FSIS and TTB, with their regulation links (FDA Food Code, 9 CFR Ch. III, 27 CFR Ch. I) | The agencies' own sites | Pending review |
| Federal minimum drinking age: 23 U.S.C. 158 conditions highway funds on the age of 21; each state sets its own law | [GovInfo, 23 U.S.C. 158](https://www.govinfo.gov/content/pkg/USCODE-2023-title23/html/USCODE-2023-title23-chap1-sec158.htm) | Pending review |
| The FDA Food Code is a model code, not federal law, and does not preempt state rules | [FDA Food Code adoption page](https://www.fda.gov/food/fda-food-code/adoption-fda-food-code-state-and-territorial-agencies-responsible-oversight-restaurants-andor-retail) | Pending review |
| Each state's and D.C.'s retail food agencies (65 rows), with oversight, Food Code edition and effective year | [FDA 2025 Food Code Adoption Annual Report, Table 5](https://www.fda.gov/media/195946/download) (data as of 31 Dec 2025) | Draft: website and state regulation still to add |
| Each state's and D.C.'s alcohol beverage authorities (64 rows) and their links | [TTB directory of alcohol beverage authorities](https://www.ttb.gov/business-central/alcohol-beverage-authorities-in-united-states-canada-and-puerto-rico) | Draft: confirm on the agency's own site |
| The 9 requirement topics for every state and D.C. | none | Verification Required |

**Before relying on the TTB list:**
- **It can lag behind changes.** Some agencies have been renamed or have moved websites since they were listed, so each record's research note asks for confirmation on the agency's own site.
- **Some links are http, not https.** They must be updated before the record can be submitted.
- **Some entries are not state ABC boards:**
  - county-level authorities in Hawaii and Maryland;
  - revenue departments;
  - one Wisconsin entry with no link.

The source files are in `backend/database/seeders/data/compliance/`. They record each source, the date it was read, and the data exactly as published.

## Research process for the remaining jurisdictions

1. **Pick the next jurisdiction.** Use the console's "Still to verify" counts, starting with states that have events.
2. **For each authority:**
   - open its own `.gov` site;
   - confirm the name and its role in food or alcohol;
   - record the website and the official statute or regulation page, for example the state administrative code or a legislature site.
3. **For each requirement topic:**
   - read the official rule;
   - summarise only what it says for that jurisdiction;
   - add the citation, the effective date and the date checked;
   - submit.

   Where nothing official can be found, leave the record as Verification Required and say what was searched in the research notes.
4. **A second administrator reviews and approves,** or sends the record back.
5. **Counties and municipalities:**
   - import them from the Census files;
   - open research on the places where events are held;
   - record the local health department and any local alcohol or special-event licensing.

## Tests

`tests/Feature/ComplianceDatabaseTest.php` has 8 tests. They cover:
- the hierarchy;
- that nothing is verified on seeding;
- the federal records and the state agencies from FDA and TTB;
- that the seed is safe to run again;
- the Census import: CDPs and territories skipped, multi-county places, a second run;
- the source rules and second-reviewer approval;
- review-due flagging;
- permissions: administrators view and submit, only system administrators approve, hosts are refused;
- adding authorities and opening research on a county.
