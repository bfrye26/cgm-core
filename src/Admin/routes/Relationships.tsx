import { useState } from 'react';
import { Plus, Trash2, Save, ShieldAlert, Play, Wrench } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Input, Select, Label, NodeChip, Notice, PageLoader, Checkbox, SubTabs, EmptyState } from '@/components/ui';
import { DataTable, type Column } from '@/components/DataTable';
import {
  useBootstrap, useSaveRelationshipDefinitions, useIntegrityOverview, useIntegrityIssues, useIntegrityRepair,
  type RelationshipDefinitionInput, type ContentType, type IntegrityRow,
} from '@/lib/hooks';
import { caps } from '@/lib/api';

type MetaDef = { label: string; type: string; options: Record<string, string>; public: boolean; required: boolean };
type EditRow = RelationshipDefinitionInput & { _key: number };

const META_TYPES = ['string', 'boolean', 'integer', 'number', 'select', 'url', 'textarea', 'date'] as const;

const optionsToString = (o: Record<string, string> | undefined) =>
  Object.entries(o ?? {}).map(([v, l]) => (v === l ? v : `${v}|${l}`)).join(', ');
const optionsFromString = (s: string) => {
  const out: Record<string, string> = {};
  s.split(',').map((t) => t.trim()).filter(Boolean).forEach((t) => {
    const [v, l] = t.split('|').map((x) => x.trim());
    if (v) out[v] = l || v;
  });
  return out;
};

function blankRow(contentTypes: ContentType[], key: number): EditRow {
  return {
    _key: key, id: '', label: '', reverse_label: 'Related content',
    source_type: contentTypes[0]?.id ?? 'post', source_types: [], target_type: contentTypes[0]?.id ?? 'post',
    cardinality: 'many_to_many', multiple: true, ordered: true, primary: false, queryable: true, public: true,
    cross_site: false, max_items: 0, primary_max: 1, delete_behavior: 'detach', roles: [],
    metadata_schema: {}, assign_capability: 'edit_posts', read_capability: 'read',
  };
}

function MetaSchemaEditor({ value, onChange, disabled }: { value: Record<string, MetaDef>; onChange: (v: Record<string, MetaDef>) => void; disabled: boolean }) {
  const [newKey, setNewKey] = useState('');
  const entries = Object.entries(value ?? {});
  const set = (key: string, patch: Partial<MetaDef>) => {
    const prev = value[key] ?? { label: key, type: 'string', options: {}, public: false, required: false };
    onChange({ ...value, [key]: { ...prev, ...patch } });
  };
  const remove = (key: string) => { const next = { ...value }; delete next[key]; onChange(next); };
  const add = () => {
    const k = newKey.trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
    if (!k || value[k]) return;
    onChange({ ...value, [k]: { label: k, type: 'string', options: {}, public: false, required: false } });
    setNewKey('');
  };

  return (
    <div className="space-y-2">
      {entries.length === 0 && <p className="text-[12.5px] text-ink-faint">No relationship metadata fields.</p>}
      {entries.map(([key, def]) => (
        <div key={key} className="space-y-2 rounded-md border border-line bg-surface-2/40 p-3">
          <div className="flex items-center gap-2">
            <NodeChip tone="amber" className="font-mono">{key}</NodeChip>
            <Input value={def.label} onChange={(e) => set(key, { label: e.target.value })} placeholder="Label" disabled={disabled} className="h-8" />
            <Select value={def.type} onChange={(e) => set(key, { type: e.target.value })} disabled={disabled} className="h-8 w-32">
              {META_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
            </Select>
            <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => remove(key)} disabled={disabled} className="h-8 w-8 p-0" aria-label="Remove field" />
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <div className="min-w-[220px] flex-1">
              <Label>Options (value|Label, …)</Label>
              <Input value={optionsToString(def.options)} onChange={(e) => set(key, { options: optionsFromString(e.target.value) })} placeholder="pc|PC, xbox|Xbox" disabled={disabled} className="h-8 font-mono text-[12px]" />
            </div>
            <Checkbox label="Public" checked={!!def.public} onChange={(v) => set(key, { public: v })} />
            <Checkbox label="Required" checked={!!def.required} onChange={(v) => set(key, { required: v })} />
          </div>
        </div>
      ))}
      {!disabled && (
        <div className="flex items-center gap-2">
          <Input value={newKey} onChange={(e) => setNewKey(e.target.value)} placeholder="new_field" className="h-8 max-w-[180px] font-mono text-[12px]" />
          <Button variant="secondary" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={add} disabled={!newKey.trim()}>Add field</Button>
        </div>
      )}
    </div>
  );
}

function ModelTab() {
  const bootstrap = useBootstrap();
  const saveDefs = useSaveRelationshipDefinitions();
  const canManage = caps().manageRelationships || caps().manage;

  const [rows, setRows] = useState<EditRow[] | null>(null);
  const [key, setKey] = useState(0);

  if (bootstrap.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const allDefinitions = bootstrap.data?.relationshipDefinitions ?? [];
  const uiManaged = allDefinitions.filter((r) => r.managed_by === 'ui');
  const providerManaged = allDefinitions.filter((r) => r.managed_by !== 'ui');

  const ensureRows = (): EditRow[] => {
    if (rows !== null) return rows;
    const fromStore = uiManaged.map((r, i) => ({
      _key: i, id: r.id, label: r.label, reverse_label: r.reverse_label, source_type: r.source_type,
      source_types: r.source_types ?? [], target_type: r.target_type, cardinality: r.cardinality,
      multiple: r.multiple, ordered: r.ordered, primary: r.primary, queryable: r.queryable, public: r.public,
      cross_site: r.cross_site, max_items: r.max_items, primary_max: r.primary_max, delete_behavior: r.delete_behavior,
      roles: r.roles ?? [], metadata_schema: (r.metadata_schema ?? {}) as Record<string, MetaDef>,
      assign_capability: r.assign_capability, read_capability: r.read_capability,
    }));
    return [...fromStore, blankRow(contentTypes, fromStore.length)];
  };

  const editRows = ensureRows();
  const update = (k: number, patch: Partial<EditRow>) => setRows((prev) => (prev ?? editRows).map((r) => (r._key === k ? { ...r, ...patch } : r)));
  const remove = (k: number) => setRows((prev) => (prev ?? editRows).filter((r) => r._key !== k));
  const add = () => { const k = key + 1; setKey(k); setRows((prev) => [...(prev ?? editRows), blankRow(contentTypes, k)]); };

  const onSave = async () => {
    const clean = editRows.filter((r) => r.id.trim()).map(({ _key: _k, ...rest }) => rest);
    await saveDefs.mutateAsync(clean);
    toast.success('Relationship model saved.');
    bootstrap.refetch();
    setRows(null);
  };

  return (
    <div className="space-y-6">
      {providerManaged.length > 0 && (
        <Card className="rise" style={{ animationDelay: '60ms' }}>
          <CardHeader title="Provider-managed" desc="Read-only relationships owned by feature plugins" />
          <CardBody className="space-y-1">
            {providerManaged.map((r) => (
              <div key={r.id} className="flex items-center gap-3 rounded-md px-2 py-2.5 transition hover:bg-surface-2/60">
                <NodeChip tone="amber">{r.label}</NodeChip>
                <code className="min-w-0 flex-1 font-mono text-[12px] text-ink-faint">{r.id}</code>
                <span className="font-mono text-[11px] text-ink-faint">{r.provider ?? 'core'}</span>
                <NodeChip tone="neutral">{r.store ?? 'core'}</NodeChip>
              </div>
            ))}
          </CardBody>
        </Card>
      )}

      <Card className="rise" style={{ animationDelay: '120ms' }}>
        <CardHeader title="Core-owned relationships" desc="Define reusable references only where WordPress has no source of truth" action={canManage ? <Button icon={<Save className="h-4 w-4" />} onClick={onSave} loading={saveDefs.isPending}>Save model</Button> : undefined} />
        <CardBody className="space-y-4">
          {!canManage && <Notice tone="gold">You do not have permission to edit the relationship model.</Notice>}

          {editRows.map((r) => (
            <div key={r._key} className="space-y-3 rounded-lg border border-line bg-surface-2/40 p-4">
              <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                  <Label>ID</Label>
                  <Input value={r.id} onChange={(e) => update(r._key, { id: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '') })} placeholder="product" disabled={!canManage} className="font-mono text-[12.5px]" />
                </div>
                <div>
                  <Label>Label</Label>
                  <Input value={r.label} onChange={(e) => update(r._key, { label: e.target.value })} disabled={!canManage} />
                </div>
                <div>
                  <Label>Reverse label</Label>
                  <Input value={r.reverse_label} onChange={(e) => update(r._key, { reverse_label: e.target.value })} disabled={!canManage} />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                  <Label>Source</Label>
                  <Select value={r.source_type} onChange={(e) => update(r._key, { source_type: e.target.value })} disabled={!canManage}>
                    <option value="*">Any registered object</option>
                    {contentTypes.map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
                  </Select>
                </div>
                <div>
                  <Label>Target</Label>
                  <Select value={r.target_type} onChange={(e) => update(r._key, { target_type: e.target.value })} disabled={!canManage}>
                    {contentTypes.map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
                  </Select>
                </div>
              </div>

              <div className="flex flex-wrap gap-5">
                <Checkbox label="Multiple" checked={r.multiple} onChange={(v) => update(r._key, { multiple: v })} />
                <Checkbox label="Ordered" checked={r.ordered} onChange={(v) => update(r._key, { ordered: v })} />
                <Checkbox label="Primary supported" checked={r.primary} onChange={(v) => update(r._key, { primary: v })} />
                <Checkbox label="Queryable" checked={r.queryable} onChange={(v) => update(r._key, { queryable: v })} />
                <Checkbox label="Public" checked={r.public} onChange={(v) => update(r._key, { public: v })} />
                <Checkbox label="Cross-site" checked={r.cross_site} onChange={(v) => update(r._key, { cross_site: v })} />
              </div>

              <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                  <Label>Roles (comma-separated)</Label>
                  <Input value={r.roles.join(', ')} onChange={(e) => update(r._key, { roles: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })} placeholder="reviewed, mentioned" disabled={!canManage} className="font-mono text-[12.5px]" />
                </div>
                <div>
                  <Label>Delete behaviour</Label>
                  <Select value={r.delete_behavior} onChange={(e) => update(r._key, { delete_behavior: e.target.value })} disabled={!canManage}>
                    <option value="detach">Detach</option>
                    <option value="restrict">Restrict</option>
                    <option value="cascade">Cascade</option>
                  </Select>
                </div>
                <div>
                  <Label>Max items (0 = unlimited)</Label>
                  <Input type="number" min={0} value={r.max_items} onChange={(e) => update(r._key, { max_items: Math.max(0, Number(e.target.value) || 0) })} disabled={!canManage} />
                </div>
              </div>

              <div>
                <Label>Relationship metadata</Label>
                <MetaSchemaEditor value={r.metadata_schema} onChange={(v) => update(r._key, { metadata_schema: v })} disabled={!canManage} />
              </div>

              {canManage && (
                <div className="flex justify-end">
                  <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => remove(r._key)}>Remove</Button>
                </div>
              )}
            </div>
          ))}

          {canManage && <Button variant="outline" icon={<Plus className="h-4 w-4" />} onClick={add}>Add relationship</Button>}
        </CardBody>
      </Card>
    </div>
  );
}

type IssueType = 'orphan_target' | 'orphan_source' | 'cardinality';

function IntegrityTab() {
  const overview = useIntegrityOverview();
  const repair = useIntegrityRepair();
  const canManage = caps().manageRelationships || caps().manage;

  const [selected, setSelected] = useState<string | null>(null);
  const [issueType, setIssueType] = useState<IssueType>('orphan_target');
  const [preview, setPreview] = useState<{ would_remove?: number; removed?: number; dry_run?: boolean } | null>(null);

  const issues = useIntegrityIssues(selected ?? '', issueType, !!selected);

  if (overview.isLoading) return <PageLoader />;

  const rows = overview.data?.items ?? [];

  const columns: Column<IntegrityRow>[] = [
    {
      key: 'rel', header: 'Relationship',
      render: (r) => <div><strong className="text-ink">{r.label}</strong><br /><code className="font-mono text-[11px] text-ink-faint">{r.id}</code></div>,
    },
    { key: 'store', header: 'Store', render: (r) => <NodeChip tone="neutral">{r.store}</NodeChip> },
    { key: 'links', header: 'Links', render: (r) => <span className="font-mono text-[12px] text-ink-soft">{r.links}</span> },
    { key: 'ot', header: 'Orphan targets', render: (r) => <NodeChip tone={r.orphan_targets ? 'rust' : 'pine'}>{r.orphan_targets}</NodeChip> },
    { key: 'os', header: 'Orphan sources', render: (r) => <NodeChip tone={r.orphan_sources ? 'rust' : 'pine'}>{r.orphan_sources}</NodeChip> },
    { key: 'cv', header: 'Cardinality', render: (r) => <NodeChip tone={r.cardinality_violations ? 'gold' : 'pine'}>{r.cardinality_violations}</NodeChip> },
    {
      key: 'actions', header: '', align: 'right',
      render: (r) => r.scannable ? <Button variant="ghost" size="sm" icon={<ShieldAlert className="h-3.5 w-3.5" />} onClick={() => { setSelected(r.id); setPreview(null); }}>Inspect</Button> : <span className="text-[12px] text-ink-faint">provider-owned</span>,
    },
  ];

  const onRepair = async (apply: boolean) => {
    if (!selected) return;
    const res = await repair.mutateAsync({ id: selected, apply });
    setPreview(res as { would_remove?: number; removed?: number; dry_run?: boolean });
    toast.success(apply ? 'Repair applied.' : 'Dry run complete.');
    issues.refetch();
  };

  const issueItems = issues.data?.items ?? [];

  return (
    <div className="space-y-6">
      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="Relationship integrity" desc="Orphaned references and cardinality violations across Core-owned relationships" />
        <CardBody className="p-0">
          <DataTable
            columns={columns} rows={rows} rowKey={(r) => r.id}
            emptyTitle="No relationships" emptyDesc="Define a relationship first to audit it."
          />
        </CardBody>
      </Card>

      {selected && (
        <Card className="rise" style={{ animationDelay: '120ms' }}>
          <CardHeader
            title={`Issues · ${selected}`}
            desc="Orphan targets = rows pointing at deleted objects; orphan sources = rows whose source no longer exists"
          />
          <CardBody className="space-y-4">
            <SubTabs
              tabs={[
                { id: 'orphan_target', label: 'Orphan targets' },
                { id: 'orphan_source', label: 'Orphan sources' },
                { id: 'cardinality', label: 'Cardinality' },
              ]}
              active={issueType}
              onChange={(t) => { setIssueType(t); setPreview(null); }}
            />

            {issueType === 'orphan_target' && (
              <div className="flex items-start gap-3">
                <Button variant="outline" icon={<Play className="h-4 w-4" />} onClick={() => onRepair(false)} loading={repair.isPending} disabled={!canManage}>Dry run repair</Button>
                {canManage && <Button variant="danger" icon={<Wrench className="h-4 w-4" />} onClick={() => onRepair(true)} loading={repair.isPending} disabled={!preview || !preview.would_remove}>Apply repair</Button>}
              </div>
            )}

            {preview && (
              <Notice tone={preview.dry_run ? 'gold' : 'pine'}>
                {preview.dry_run
                  ? `${preview.would_remove ?? 0} broken reference(s) would be removed. Review the list below, then apply.`
                  : `${preview.removed ?? 0} broken reference(s) removed.`}
              </Notice>
            )}

            {issueType === 'orphan_target' && issueItems.length === 0 && issues.isSuccess && (
              <EmptyState icon={<ShieldAlert className="h-5 w-5" />} title="No orphan targets" desc="Every target reference resolves to a live object." />
            )}

            {issueItems.length > 0 && (
              <div className="space-y-1">
                {issueItems.map((item, i) => {
                  const target = issueType === 'orphan_target' ? `#${item.target_id}` : issueType === 'orphan_source' ? `#${item.source_id}` : `#${item.source_id} × ${item.count}`;
                  const type = (item.target_type ?? item.source_type ?? '') as string;
                  return (
                    <div key={i} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                      <NodeChip tone="rust">{type}</NodeChip>
                      <code className="font-mono text-[12px] text-ink-soft">{target}</code>
                      {issueType === 'cardinality' && <span className="font-mono text-[11px] text-ink-faint">limit {item.limit as number}</span>}
                    </div>
                  );
                })}
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export function RelationshipsPage() {
  const [tab, setTab] = useState<'model' | 'integrity'>('model');

  return (
    <div className="space-y-6">
      <div className="rise flex items-end justify-between gap-3">
        <div>
          <p className="eyebrow">Entity references</p>
          <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Relationships</h2>
          <p className="mt-1 text-[13px] text-ink-faint">Feature plugins may own storage; Core owns the common schema, query and builder contract.</p>
        </div>
      </div>

      <SubTabs tabs={[{ id: 'model', label: 'Model' }, { id: 'integrity', label: 'Integrity' }]} active={tab} onChange={setTab} />

      {tab === 'model' ? <ModelTab /> : <IntegrityTab />}
    </div>
  );
}
