import React, { useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  Copy,
  Layers,
  Lightbulb,
  Repeat,
  ShieldCheck,
  Sparkles,
  Users,
} from "lucide-react";
import { EventDetails, EventWizardStepId, MenuItem } from "../../types";
import {
  EVENT_ARCHITECTURE_PILLARS,
  EVENT_STUDIO_MODULES,
  EventArchitecturePillarId,
  EventStudioModuleId,
  evaluateEventIntelligenceAndReadiness,
  GROWTH_PLATFORM_CAPABILITIES,
  IMPLEMENTATION_ROADMAP_PHASES,
  RECOMMENDATION_SEVERITY_META,
  RecommendationSeverity,
} from "../../lib/eventIntelligence";

interface EventIntelligencePanelProps {
  eventDetails: EventDetails;
  menu: MenuItem[];
  currentStep?: EventWizardStepId;
  onNavigateToStep?: (
    step: EventWizardStepId,
    studioModuleId?: EventStudioModuleId
  ) => void;
  onDuplicateEvent?: () => void;
  onSaveAsTemplate?: (templateName: string) => void;
}

export const EventIntelligencePanel: React.FC<EventIntelligencePanelProps> = ({
  eventDetails,
  menu,
  currentStep,
  onNavigateToStep,
  onDuplicateEvent,
  onSaveAsTemplate,
}) => {
  const report = evaluateEventIntelligenceAndReadiness(eventDetails, menu);

  const [activeStudioModule, setActiveStudioModule] =
    useState<EventStudioModuleId>("event_identity");
  const [activePillar, setActivePillar] =
    useState<EventArchitecturePillarId>("event_creation");
  const [severityFilter, setSeverityFilter] = useState<
    "all" | RecommendationSeverity
  >("all");
  const [enabledGrowthIds, setEnabledGrowthIds] = useState<string[]>(() =>
    GROWTH_PLATFORM_CAPABILITIES.filter((c) => c.defaultEnabled).map(
      (c) => c.id
    )
  );
  const [showGrowthPanel, setShowGrowthPanel] = useState(false);
  const [templateToast, setTemplateToast] = useState<string | null>(null);

  const filteredRecommendations =
    severityFilter === "all"
      ? report.recommendations
      : report.recommendations.filter((r) => r.severity === severityFilter);

  const handleSelectStudioModule = (modId: EventStudioModuleId) => {
    setActiveStudioModule(modId);
    const found = EVENT_STUDIO_MODULES.find((m) => m.id === modId);
    if (found) {
      setActivePillar(found.architecturalPillar);
      onNavigateToStep?.(found.mappedWizardStep, modId);
    }
  };

  const handleToggleGrowthCapability = (id: string) => {
    setEnabledGrowthIds((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    );
  };

  return (
    <section
      id="event-intelligence-panel"
      data-testid="event-intelligence-panel"
      className="rounded-2xl border border-lime-400/25 bg-[#0E131F] p-5 text-white shadow-xl"
    >
      {/* 1. Architectural Separation Bar (Creation vs. Configuration vs. Operations) */}
      <div
        id="event-architecture-pillars-bar"
        className="mb-5 rounded-xl border border-white/10 bg-black/40 p-3.5"
      >
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-lime-400">
            <Layers className="h-4 w-4" />
            Modular Architecture: Event Creation · Configuration · Operations
          </div>
          <span className="text-[11px] text-zinc-400">
            All modules share the same event record ({eventDetails.id || "draft"}) & RBAC permissions
          </span>
        </div>

        <div className="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
          {EVENT_ARCHITECTURE_PILLARS.map((pillar) => {
            const isActive = activePillar === pillar.id;
            return (
              <button
                key={pillar.id}
                type="button"
                data-testid={`arch-pillar-${pillar.id}`}
                onClick={() => setActivePillar(pillar.id)}
                className={`rounded-lg border p-2 text-left transition ${
                  isActive
                    ? "border-lime-400/50 bg-lime-400/15 text-white"
                    : "border-white/10 bg-zinc-900/70 text-zinc-300 hover:border-white/20"
                }`}
              >
                <div className="text-xs font-extrabold">{pillar.title}</div>
                <div className="mt-0.5 line-clamp-2 text-[10px] text-zinc-400">
                  {pillar.purpose}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* 2. Progressive 10-Module EventBite Event Studio Navigation */}
      <div
        id="eventbite-event-studio-nav"
        data-testid="eventbite-event-studio-nav"
        className="mb-6 rounded-xl border border-purple-500/25 bg-black/30 p-4"
      >
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <span className="text-[11px] font-extrabold uppercase tracking-wider text-purple-300">
              EventBite Event Studio · 10-Module Progressive Navigation
            </span>
            <p className="text-xs text-zinc-400">
              Navigate directly to any specialized module without an overwhelming single-page form.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              id="btn-duplicate-event-config"
              onClick={() => {
                onDuplicateEvent?.();
                setTemplateToast(
                  "Event settings, ticket tiers, and food menu duplicated into a ready-to-edit copy."
                );
              }}
              className="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-bold text-zinc-200 hover:bg-white/10"
            >
              <Copy className="h-3.5 w-3.5 text-lime-400" />
              Duplicate Event
            </button>
            <button
              type="button"
              id="btn-save-event-template"
              onClick={() => {
                onSaveAsTemplate?.(
                  `${eventDetails.category || "Cultural"} Event Template`
                );
                setTemplateToast(
                  `Saved "${eventDetails.title || "Event"}" as a reusable EventBite Studio Template.`
                );
              }}
              className="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/30 bg-purple-500/15 px-3 py-1.5 text-xs font-bold text-purple-200 hover:bg-purple-500/25"
            >
              <Repeat className="h-3.5 w-3.5" />
              Save as Template
            </button>
          </div>
        </div>

        {templateToast && (
          <div className="mt-2.5 rounded-lg border border-lime-400/30 bg-lime-400/10 px-3 py-1.5 text-xs font-semibold text-lime-300">
            ✓ {templateToast}
          </div>
        )}

        <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
          {EVENT_STUDIO_MODULES.map((mod) => {
            const isSelected =
              activeStudioModule === mod.id ||
              (currentStep === mod.mappedWizardStep &&
                activeStudioModule === mod.id);
            return (
              <button
                key={mod.id}
                type="button"
                id={`studio-module-btn-${mod.id}`}
                data-testid={`studio-module-${mod.number}`}
                onClick={() => handleSelectStudioModule(mod.id)}
                className={`flex flex-col justify-between rounded-xl border p-2.5 text-left transition ${
                  isSelected
                    ? "border-lime-400 bg-lime-400/15 text-white shadow-sm"
                    : "border-white/10 bg-zinc-900/80 text-zinc-300 hover:border-purple-400/40"
                }`}
              >
                <div className="flex items-center justify-between">
                  <span className="rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-extrabold text-lime-300">
                    {mod.number}
                  </span>
                  <ArrowRight className="h-3 w-3 text-zinc-400" />
                </div>
                <div className="mt-1.5 text-xs font-extrabold text-white">
                  {mod.title}
                </div>
                <div className="mt-0.5 line-clamp-2 text-[10px] leading-snug text-zinc-400">
                  {mod.description}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* 3. Event Readiness Score + Automated Recommendations Grid */}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
        {/* Left: Event Readiness Score & 5-Domain Breakdown */}
        <div
          id="event-readiness-score-card"
          data-testid="event-readiness-score-card"
          className="rounded-xl border border-white/10 bg-black/40 p-4 lg:col-span-5"
        >
          <div className="flex items-center justify-between">
            <div>
              <span className="text-xs font-extrabold uppercase tracking-wider text-lime-400">
                Event readiness
              </span>
              <h4 className="mt-0.5 text-sm font-bold text-white">
                Testable Publishing Readiness Score
              </h4>
            </div>
            <div
              data-testid="event-readiness-percentage"
              className={`flex h-14 w-14 items-center justify-center rounded-2xl border text-lg font-black ${
                report.scorePercent >= 85
                  ? "border-emerald-400/50 bg-emerald-500/20 text-emerald-300"
                  : report.scorePercent >= 60
                  ? "border-amber-400/50 bg-amber-500/20 text-amber-300"
                  : "border-red-400/50 bg-red-500/20 text-red-300"
              }`}
            >
              {report.scorePercent}%
            </div>
          </div>

          {/* Progress bar */}
          <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-zinc-800">
            <div
              className="h-full rounded-full bg-gradient-to-r from-lime-400 to-emerald-400 transition-all"
              style={{ width: `${report.scorePercent}%` }}
            />
          </div>

          {/* 5 Canonical Readiness Domains */}
          <div className="mt-4 space-y-2">
            {report.domains.map((domain) => {
              const isComplete = domain.status === "Complete";
              return (
                <div
                  key={domain.key}
                  data-testid={`readiness-domain-${domain.key}`}
                  className="flex items-center justify-between rounded-lg border border-white/10 bg-zinc-900/80 px-3 py-2 text-xs"
                >
                  <span className="font-semibold text-zinc-200">
                    {domain.label}
                  </span>
                  <span
                    className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${
                      isComplete
                        ? "bg-emerald-500/20 text-emerald-300"
                        : "bg-amber-500/20 text-amber-300"
                    }`}
                  >
                    {isComplete ? (
                      <CheckCircle2 className="h-3 w-3" />
                    ) : (
                      <AlertTriangle className="h-3 w-3" />
                    )}
                    {domain.status}
                  </span>
                </div>
              );
            })}
          </div>

          <p className="mt-3 text-[11px] text-zinc-400">
            Calculated from defined publishing rules across identity, capacity vs. ticket inventory, food commerce, marketing assets, and refund policies.
          </p>
        </div>

        {/* Right: Automated Recommendations Panel */}
        <div
          id="automated-recommendations-list"
          className="rounded-xl border border-white/10 bg-black/40 p-4 lg:col-span-7"
        >
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-3">
            <div>
              <div className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-purple-300">
                <Sparkles className="h-4 w-4 text-lime-400" />
                Event Intelligence & Automated Recommendations
              </div>
              <p className="mt-0.5 text-xs text-zinc-400">
                Real-time diagnostic checks across capacity, ticket windows, VIP perks, food pickup, images, and packages.
              </p>
            </div>

            {/* Severity filter pills */}
            <div className="flex flex-wrap gap-1.5 text-[11px]">
              <button
                type="button"
                onClick={() => setSeverityFilter("all")}
                className={`rounded-lg px-2.5 py-1 font-bold ${
                  severityFilter === "all"
                    ? "bg-white text-black"
                    : "bg-zinc-900 text-zinc-300"
                }`}
              >
                All ({report.recommendations.length})
              </button>
              <button
                type="button"
                onClick={() => setSeverityFilter("critical")}
                className={`rounded-lg px-2.5 py-1 font-bold ${
                  severityFilter === "critical"
                    ? "bg-red-500 text-white"
                    : "bg-red-500/15 text-red-300"
                }`}
              >
                Critical ({report.criticalCount})
              </button>
              <button
                type="button"
                onClick={() => setSeverityFilter("warning")}
                className={`rounded-lg px-2.5 py-1 font-bold ${
                  severityFilter === "warning"
                    ? "bg-amber-400 text-black"
                    : "bg-amber-500/15 text-amber-300"
                }`}
              >
                Warning ({report.warningCount})
              </button>
              <button
                type="button"
                onClick={() => setSeverityFilter("suggestion")}
                className={`rounded-lg px-2.5 py-1 font-bold ${
                  severityFilter === "suggestion"
                    ? "bg-sky-400 text-black"
                    : "bg-sky-500/15 text-sky-300"
                }`}
              >
                Suggestion ({report.suggestionCount})
              </button>
            </div>
          </div>

          {/* Severity Legend */}
          <div className="mt-2.5 flex flex-wrap gap-4 text-[11px] text-zinc-400">
            <span>
              <strong className="text-red-400">Critical:</strong>{" "}
              {RECOMMENDATION_SEVERITY_META.critical.subtitle}
            </span>
            <span>
              <strong className="text-amber-300">Warning:</strong>{" "}
              {RECOMMENDATION_SEVERITY_META.warning.subtitle}
            </span>
            <span>
              <strong className="text-sky-300">Suggestion:</strong>{" "}
              {RECOMMENDATION_SEVERITY_META.suggestion.subtitle}
            </span>
          </div>

          {/* Recommendations list */}
          <div className="mt-3 max-h-72 space-y-2.5 overflow-y-auto pr-1">
            {filteredRecommendations.length === 0 ? (
              <div className="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-semibold text-emerald-300">
                <ShieldCheck className="h-4 w-4 shrink-0" />
                No issues in this category—your event configuration looks great!
              </div>
            ) : (
              filteredRecommendations.map((rec) => {
                const meta = RECOMMENDATION_SEVERITY_META[rec.severity];
                return (
                  <div
                    key={rec.id}
                    data-testid={`intelligence-rec-${rec.ruleCode}`}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-zinc-900/90 p-3"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span
                          className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase ${
                            rec.severity === "critical"
                              ? "bg-red-500/20 text-red-300"
                              : rec.severity === "warning"
                              ? "bg-amber-500/20 text-amber-300"
                              : "bg-sky-500/20 text-sky-300"
                          }`}
                        >
                          {rec.severity === "critical" && (
                            <AlertCircle className="h-3 w-3" />
                          )}
                          {rec.severity === "warning" && (
                            <AlertTriangle className="h-3 w-3" />
                          )}
                          {rec.severity === "suggestion" && (
                            <Lightbulb className="h-3 w-3" />
                          )}
                          {meta.label}
                        </span>
                        <span className="text-[11px] text-zinc-400">
                          {meta.subtitle}
                        </span>
                      </div>
                      <p className="mt-1 text-xs font-bold text-white">
                        {rec.message}
                      </p>
                    </div>

                    <button
                      type="button"
                      onClick={() =>
                        onNavigateToStep?.(
                          rec.targetWizardStep,
                          rec.targetStudioModuleId
                        )
                      }
                      className="rounded-lg border border-lime-400/40 bg-lime-400/10 px-3 py-1.5 text-xs font-bold text-lime-300 hover:bg-lime-400/20"
                    >
                      {rec.actionLabel}
                    </button>
                  </div>
                );
              })
            )}
          </div>
        </div>
      </div>

      {/* 4. Expandable Platform Growth Capabilities & Phased Implementation Roadmap */}
      <div className="mt-5 border-t border-white/10 pt-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2 text-xs font-bold text-zinc-300">
            <Users className="h-4 w-4 text-lime-400" />
            <span>
              Growth & Multi-Event Capabilities (Recurring Events, Co-Hosts, Templates, Waitlist, Loyalty & Roadmap)
            </span>
          </div>
          <button
            type="button"
            id="btn-toggle-growth-capabilities"
            onClick={() => setShowGrowthPanel((prev) => !prev)}
            className="rounded-lg border border-white/15 bg-white/5 px-3 py-1 text-xs font-bold text-lime-300 hover:bg-white/10"
          >
            {showGrowthPanel
              ? "Hide Growth Capabilities & Phased Roadmap"
              : "Configure Growth Capabilities & View Phased Roadmap"}
          </button>
        </div>

        {showGrowthPanel && (
          <div
            id="growth-capabilities-drawer"
            className="mt-4 space-y-4 rounded-xl border border-white/10 bg-black/30 p-4"
          >
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
              {GROWTH_PLATFORM_CAPABILITIES.map((cap) => {
                const enabled = enabledGrowthIds.includes(cap.id);
                return (
                  <label
                    key={cap.id}
                    data-testid={`growth-capability-${cap.id}`}
                    className="flex cursor-pointer items-start justify-between gap-2 rounded-lg border border-white/10 bg-zinc-900/80 p-2.5 text-xs"
                  >
                    <div>
                      <div className="font-bold text-white">{cap.title}</div>
                      <div className="mt-0.5 text-[11px] text-zinc-400">
                        {cap.description}
                      </div>
                    </div>
                    <input
                      type="checkbox"
                      checked={enabled}
                      onChange={() => handleToggleGrowthCapability(cap.id)}
                      className="mt-0.5"
                    />
                  </label>
                );
              })}
            </div>

            {/* 5-Phase Implementation Priorities Table */}
            <div className="rounded-lg border border-white/10 bg-zinc-950/70 p-3">
              <div className="text-xs font-extrabold uppercase tracking-wider text-purple-300">
                EventBite 5-Phase Implementation Architecture
              </div>
              <div className="mt-2 grid grid-cols-1 gap-2 md:grid-cols-5">
                {IMPLEMENTATION_ROADMAP_PHASES.map((p) => (
                  <div
                    key={p.phase}
                    className="rounded-lg border border-white/10 bg-zinc-900/80 p-2.5 text-xs"
                  >
                    <div className="font-extrabold text-lime-400">
                      {p.phase}
                    </div>
                    <div className="mt-1 font-semibold text-white">
                      {p.features}
                    </div>
                    <div className="mt-1 text-[10px] text-zinc-400">
                      {p.reason}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </section>
  );
};

