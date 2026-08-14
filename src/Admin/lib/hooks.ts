import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiGet, apiPost } from './api';

/* ── Registry / bootstrap types ─────────────────────────────────────── */
export interface ContentType {
  id: string;
  label: string;
  plural_label?: string;
  kind?: string;
  public?: boolean;
}
export interface Field {
  id: string;
  label: string;
  type: string;
  operators?: string[];
  provider?: string;
  content_types?: string[];
  sortable?: boolean;
  options?: Record<string, string>;
  source?: string;
}
export interface RelProperty {
  id: string;
  label: string;
  type: string;
  options?: Record<string, string>;
}
export interface Relationship {
  id: string;
  label: string;
  source_type?: string;
  source_types?: string[];
  target_type?: string;
  operators?: string[];
  roles?: string[];
  metadata_schema?: Record<string, { label?: string; type?: string; options?: Record<string, string> }>;
  properties?: RelProperty[];
  queryable?: boolean;
  provider?: string;
  managed_by?: string;
  store?: string;
}
export interface Taxonomy {
  id: string;
  label: string;
  content_types?: string[];
  operators?: string[];
}
export interface Provider {
  id: string;
  label?: string;
  status?: string;
  version?: string;
  capabilities?: string[];
  compatibility?: Record<string, unknown>;
}
export interface Builder {
  id: string;
  label?: string;
  detected?: boolean;
  integration_level?: string;
  capabilities?: string[];
}
export interface SavedQuerySummary {
  id: number | string;
  slug: string;
  title: string;
  public: boolean;
  managed_by: string;
  readonly?: boolean;
  usage?: number;
}
export interface DynamicDataDef {
  id: string;
  label?: string;
  type?: string;
  group?: string;
  provider?: string;
  description?: string;
  public?: boolean;
}
export interface Bootstrap {
  version: string;
  api: Record<string, string>;
  schema: string;
  caps: {
    manage: boolean;
    manageQueries: boolean;
    manageRelationships: boolean;
    manageConfig: boolean;
    inspectData: boolean;
  };
  counts: {
    providers: number;
    content_types: number;
    fields: number;
    relationships: number;
    queries: number;
    builders: number;
  };
  contentTypes: ContentType[];
  fields: Field[];
  relationships: Relationship[];
  relationshipDefinitions: RelationshipDefinition[];
  taxonomies: Taxonomy[];
  tokens: Record<string, string>;
  providers: Provider[];
  providersCompatible: Record<string, unknown>;
  builders: Builder[];
  queryProviders: string[];
  multisite: Record<string, unknown>;
  savedQueries: SavedQuerySummary[];
  dynamicData: DynamicDataDef[];
  viewModes: { id: string; label?: string; fields?: (string | { field?: string; path?: string; label?: string })[] }[];
}

/* ── Query definition model ─────────────────────────────────────────── */
export interface QueryRule {
  type: 'field' | 'relationship' | 'relationship_property' | 'path' | 'taxonomy' | 'relationship_reverse' | 'relationship_count';
  field?: string;
  relationship?: string;
  property?: string;
  path?: string;
  taxonomy?: string;
  operator?: string;
  value?: string | string[] | number | boolean;
  reverse?: boolean;
}
export interface QueryGroup {
  relation: 'AND' | 'OR';
  rules: (QueryRule | QueryGroup)[];
}
export interface ExposedFilter {
  field: string;
  label?: string;
  input?: 'text' | 'select';
  options?: Record<string, string>;
}
export interface QueryDefinition {
  content_type: string;
  status?: string[];
  search?: string;
  filters?: QueryGroup;
  sort?: { field?: string; path?: string; order: string }[];
  limit: number;
  page: number;
  offset: number;
  cache: boolean;
  cache_ttl: number;
  display?: string;
  exposed_filters?: ExposedFilter[];
  aggregate?: { group_by: string; function: string; field?: string; limit: number };
}

export const defaultDefinition = (contentType = 'post'): QueryDefinition => ({
  content_type: contentType,
  status: ['publish'],
  search: '',
  filters: { relation: 'AND', rules: [] },
  sort: [{ field: 'post.date', order: 'DESC' }],
  limit: 12,
  page: 1,
  offset: 0,
  cache: true,
  cache_ttl: 120,
  display: 'list',
  exposed_filters: [],
});

export interface QueryRunItem {
  id: number;
  label?: string;
  [key: string]: unknown;
}
export interface QueryRunResult {
  items: QueryRunItem[];
  total: number;
  page: number;
  per_page: number;
  debug?: Record<string, unknown>;
  error?: string;
}

/* ── Bootstrap ──────────────────────────────────────────────────────── */
export function useBootstrap() {
  return useQuery({ queryKey: ['bootstrap'], queryFn: () => apiGet<Bootstrap>('bootstrap') });
}

/* ── Saved query CRUD ───────────────────────────────────────────────── */
export function useSaveQuery() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { id?: number; title: string; public: boolean; definition: QueryDefinition }) =>
      apiPost<{ success: boolean; id: number | string; slug?: string }>('queries', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['queries'] }),
  });
}
export function useDeleteQuery() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number | string) => apiPost<{ success: boolean }>(`queries/${encodeURIComponent(String(id))}/delete`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['queries'] }),
  });
}
export function useCloneQuery() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number | string) => apiPost<{ success: boolean; id: number | string }>(`queries/${encodeURIComponent(String(id))}/clone`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['queries'] }),
  });
}

/* ── Query test / explain ───────────────────────────────────────────── */
export function useTestQuery() {
  return useMutation({
    mutationFn: (payload: { query: QueryDefinition; context?: Record<string, unknown> }) =>
      apiPost<QueryRunResult>('query/test', payload),
  });
}
export function useExplainQuery() {
  return useMutation({
    mutationFn: (payload: { query: QueryDefinition; context?: Record<string, unknown> }) =>
      apiPost<Record<string, unknown>>('query/explain', payload),
  });
}
export interface AggregateRow { label: string; total?: number; [k: string]: unknown }
export function useAggregate() {
  return useMutation({
    mutationFn: (payload: { query: QueryDefinition; context?: Record<string, unknown> }) =>
      apiPost<{ group_by: string; function: string; rows: AggregateRow[]; error?: string }>('query/aggregate', payload),
  });
}

/* ── Relationships (definitions) ────────────────────────────────────── */
export interface RelationshipDefinitionInput {
  id: string;
  label: string;
  reverse_label: string;
  source_type: string;
  source_types: string[];
  target_type: string;
  cardinality: string;
  multiple: boolean;
  ordered: boolean;
  primary: boolean;
  queryable: boolean;
  public: boolean;
  cross_site: boolean;
  max_items: number;
  primary_max: number;
  delete_behavior: string;
  roles: string[];
  metadata_schema: Record<string, { label: string; type: string; options: Record<string, string>; public: boolean; required: boolean }>;
  assign_capability: string;
  read_capability: string;
}
export interface RelationshipDefinition extends RelationshipDefinitionInput {
  provider: string;
  store: string;
  managed_by: string;
}
export function useSaveRelationshipDefinitions() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (rows: RelationshipDefinitionInput[]) => apiPost<{ success: boolean }>('relationships/definitions', { rows }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bootstrap'] }),
  });
}

/* ── Object inspector ───────────────────────────────────────────────── */
export function useSearchObjects(contentType: string, search: string, enabled = true) {
  return useQuery({
    queryKey: ['objects', contentType, search],
    queryFn: () => apiGet<{ items: { id: number; label?: string; [k: string]: unknown }[] }>(`objects/search?content_type=${encodeURIComponent(contentType)}&search=${encodeURIComponent(search)}`),
    enabled: enabled && search.length > 0,
  });
}
export function useObject(contentType: string, id: number) {
  return useQuery({
    queryKey: ['object', contentType, id],
    queryFn: () => apiGet<Record<string, unknown>>(`objects/${encodeURIComponent(contentType)}/${id}`),
    enabled: id > 0,
  });
}
export function useResolveData(key: string, objectId: number, contentType: string, enabled = true) {
  return useQuery({
    queryKey: ['data', key, objectId, contentType],
    queryFn: () => apiGet<{ value: unknown; type: string }>(`data/${encodeURIComponent(key)}?object_id=${objectId}&content_type=${encodeURIComponent(contentType)}`),
    enabled: enabled && !!key && objectId > 0,
  });
}

/* ── Search indexes ─────────────────────────────────────────────────── */
export interface IndexDef { id: string; label?: string; content_types?: string[]; provider?: string }
export function useIndexes() {
  return useQuery({ queryKey: ['indexes'], queryFn: () => apiGet<{ items: IndexDef[] }>('indexes') });
}
export function useRebuildIndexes() {
  return useMutation({ mutationFn: (index?: string) => apiPost<{ rebuilding: number }>('indexes/rebuild', { index: index ?? '' }) });
}

/* ── Automation rules ───────────────────────────────────────────────── */
export interface RuleCondition { type: 'field' | 'relationship'; field?: string; relationship?: string; operator?: string; value?: string }
export interface RuleAction {
  type: 'dispatch' | 'reindex' | 'purge' | 'action' | 'set_meta' | 'set_term' | 'set_status' | 'add_relationship' | 'notify' | 'webhook' | 'schedule' | 'add_term';
  event?: string;
  index?: string;
  tag?: string;
  action?: string;
  meta_key?: string;
  meta_value?: string;
  taxonomy?: string;
  term?: string;
  status?: string;
  relationship?: string;
  target?: string;
  to?: string;
  subject?: string;
  message?: string;
  url?: string;
  delay?: number;
}
export interface Rule { id: string; label: string; event: string; enabled: boolean; conditions: RuleCondition[]; actions: RuleAction[] }
export function useRules(enabled = true) {
  return useQuery({
    queryKey: ['rules'],
    queryFn: () => apiGet<{ rules: Rule[]; events: { id: string; label: string }[]; actions: { id: string; label: string }[] }>('rules'),
    enabled,
  });
}
export function useSaveRules() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (rules: Rule[]) => apiPost<{ success: boolean }>('rules', { rules }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['rules'] }),
  });
}

/* ── Bulk operations ────────────────────────────────────────────────── */
export function useBulkPreview() {
  return useMutation({
    mutationFn: (payload: { query: string | QueryDefinition; actions?: RuleAction[] }) =>
      apiPost<{ count: number; sample: { id: number; type: string; label: string }[] }>('bulk/preview', payload),
  });
}
export function useBulkRun() {
  return useMutation({
    mutationFn: (payload: { query: string | QueryDefinition; actions: RuleAction[] }) =>
      apiPost<{ processed: number; succeeded: number; failed: number }>('bulk/run', payload),
  });
}

/* ── Workflow states ────────────────────────────────────────────────── */
export interface WorkflowState { id: string; label: string; color: string; order: number; transitions: string[] }
export function useWorkflowStates() {
  return useQuery({ queryKey: ['workflow-states'], queryFn: () => apiGet<{ states: WorkflowState[] }>('workflow/states') });
}
export function useWorkflowTransition() {
  return useMutation({
    mutationFn: (payload: { object_id: number; state: string }) => apiPost<{ success: boolean; state: string }>('workflow/transition', payload),
  });
}

export interface ScheduledTransition { id: string; post_id: number; post_title: string; state: string; state_label: string; at: number }
export function useScheduledWorkflow() {
  return useQuery({ queryKey: ['workflow-scheduled'], queryFn: () => apiGet<{ items: ScheduledTransition[] }>('workflow/scheduled') });
}
export function useScheduleWorkflow() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { object_id: number; state: string; at: string }) => apiPost<{ success: boolean }>('workflow/schedule', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['workflow-scheduled'] }),
  });
}
export function useCancelScheduledWorkflow() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => apiPost<{ success: boolean }>(`workflow/scheduled/${encodeURIComponent(id)}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['workflow-scheduled'] }),
  });
}

export interface AutoTransitionRule { id: string; content_types: string[]; from_state: string; to_state: string; after_days: number; enabled: boolean }
export function useAutoTransitions() {
  return useQuery({ queryKey: ['workflow-auto'], queryFn: () => apiGet<{ rules: AutoTransitionRule[] }>('workflow/auto-transitions') });
}
export function useSaveAutoTransitions() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (rules: AutoTransitionRule[]) => apiPost<{ success: boolean }>('workflow/auto-transitions', { rules }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['workflow-auto'] }),
  });
}
export function useRunAutoTransitions() {
  return useMutation({ mutationFn: () => apiPost<{ transitioned: number }>('workflow/auto-transitions/run') });
}

/* ── Search / facets / graph ────────────────────────────────────────── */
export interface Facet { id: string; label: string; taxonomy: string; options: { value: string; label: string; count: number }[] }
export function useFacets(contentType: string) {
  return useQuery({ queryKey: ['facets', contentType], queryFn: () => apiGet<{ facets: Facet[] }>(`facets?content_type=${encodeURIComponent(contentType)}`) });
}
export function useSearch(q: string, contentType: string, filters: Record<string, string[]>, enabled = true) {
  return useQuery({
    queryKey: ['search', q, contentType, filters],
    queryFn: () => apiGet<{ items: { id: number; label?: string; url?: string; [k: string]: unknown }[]; total: number }>(`search?q=${encodeURIComponent(q)}&content_type=${encodeURIComponent(contentType)}&filters=${encodeURIComponent(JSON.stringify(filters))}`),
    enabled: enabled && q.length > 0,
  });
}
export function useGraph(contentType: string, id: number, depth: number) {
  return useQuery({
    queryKey: ['graph', contentType, id, depth],
    queryFn: () => apiGet<{ nodes: { id: number; type: string; label: string }[]; edges: { source: string; target: string; label: string }[] }>(`graph/${encodeURIComponent(contentType)}/${id}?depth=${depth}`),
    enabled: id > 0,
  });
}

/* ── Notifications ──────────────────────────────────────────────────── */
export interface NotificationItem { id: string; title: string; message: string; type: string; created: string }
export function useNotifications() {
  return useQuery({ queryKey: ['notifications'], queryFn: () => apiGet<{ items: NotificationItem[] }>('notifications') });
}
export function useDismissNotification() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => apiPost<{ success: boolean }>(`notifications/${encodeURIComponent(id)}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });
}

/* ── Config ─────────────────────────────────────────────────────────── */
export function useConfigBackups(enabled = true) {
  return useQuery({ queryKey: ['config-backups'], queryFn: () => apiGet<{ backups: { id: string; created: string }[]; pending?: unknown }>('config/backups'), enabled });
}
export function useConfigExport(enabled = true) {
  return useQuery({ queryKey: ['config-export'], queryFn: () => apiGet<Record<string, unknown>>('config/export'), enabled });
}
export function useConfigDiff() {
  return useMutation({
    mutationFn: (payload: { config: Record<string, unknown>; mode: 'merge' | 'replace' }) => apiPost<Record<string, unknown>>('config/diff', payload),
  });
}
export function useConfigImport() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { config: Record<string, unknown>; mode: 'merge' | 'replace'; dry_run: boolean }) =>
      apiPost<Record<string, unknown>>('config/import', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['config-backups'] }),
  });
}
export function useConfigRollback() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => apiPost<Record<string, unknown>>(`config/rollback/${encodeURIComponent(id)}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['config-backups'] }),
  });
}

/* ── Activity / performance ─────────────────────────────────────────── */
export interface ActivityEntry {
  event: string;
  occurred_at: string;
  summary: string;
}
export function useActivity(limit = 50) {
  return useQuery({ queryKey: ['activity', limit], queryFn: () => apiGet<{ activity: ActivityEntry[] }>(`activity?limit=${limit}`) });
}
export interface PerfEntry {
  slug: string;
  count: number;
  total_ms: number;
  slowest_ms: number;
  cache_hits: number;
  last_run: string;
}
export function usePerformance() {
  return useQuery({ queryKey: ['performance'], queryFn: () => apiGet<{ queries: PerfEntry[] }>('performance') });
}

/* ── Relationship integrity ─────────────────────────────────────────── */
export interface IntegrityRow {
  id: string;
  label: string;
  store: string;
  scannable: boolean;
  links: number;
  orphan_targets: number;
  orphan_sources: number;
  cardinality_violations: number;
}
export function useIntegrityOverview() {
  return useQuery({ queryKey: ['integrity'], queryFn: () => apiGet<{ items: IntegrityRow[] }>('integrity/overview') });
}
export function useIntegrityIssues(id: string, type: string, enabled = true) {
  return useQuery({
    queryKey: ['integrity', id, type],
    queryFn: () => apiGet<{ items: Record<string, string | number>[]; count: number }>(`integrity/${encodeURIComponent(id)}/issues?type=${type}`),
    enabled: enabled && !!id,
  });
}
export function useIntegrityRepair() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { id: string; apply: boolean }) => apiPost<Record<string, unknown>>(`integrity/${encodeURIComponent(payload.id)}/repair`, { apply: payload.apply }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['integrity'] }),
  });
}

/* ── Single saved query (definition + usage) ────────────────────────── */
export interface SavedQueryFull {
  id: number | string;
  slug: string;
  title: string;
  public: boolean;
  managed_by: string;
  readonly?: boolean;
  definition: QueryDefinition;
  usage?: number;
  usage_rows?: { consumer: string; location: string; last_used: string; count: number }[];
}
export function useSavedQuery(id: string, enabled = true) {
  return useQuery({
    queryKey: ['query', id],
    queryFn: () => apiGet<SavedQueryFull>(`queries/${encodeURIComponent(id)}`),
    enabled: enabled && !!id,
  });
}
