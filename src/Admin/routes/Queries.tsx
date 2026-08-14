import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Play, Braces, Save, Trash2, Copy, Download, FileJson, Link2 } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Input, Select, Label, NodeChip, PageLoader, ToggleRow } from '@/components/ui';
import { DataTable, type Column } from '@/components/DataTable';
import { QueryBuilder } from '@/components/query-builder';
import {
  useBootstrap, useSaveQuery, useDeleteQuery, useCloneQuery, useTestQuery, useExplainQuery, useSavedQuery,
  defaultDefinition, type QueryDefinition, type QueryRunItem, type SavedQuerySummary,
} from '@/lib/hooks';
import { caps, apiDownload, apiGet } from '@/lib/api';

function triggerDownload(blob: Blob, name: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function QueriesPage() {
  const navigate = useNavigate();
  const bootstrap = useBootstrap();
  const saveQuery = useSaveQuery();
  const deleteQuery = useDeleteQuery();
  const cloneQuery = useCloneQuery();
  const testQuery = useTestQuery();
  const explainQuery = useExplainQuery();

  const [editingSlug, setEditingSlug] = useState('');
  const [editingId, setEditingId] = useState<number | string | null>(null);
  const [title, setTitle] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [definition, setDefinition] = useState<QueryDefinition>(defaultDefinition());
  const [results, setResults] = useState<QueryRunItem[] | null>(null);
  const [total, setTotal] = useState(0);
  const [explain, setExplain] = useState<Record<string, unknown> | null>(null);
  const [explainOpen, setExplainOpen] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<SavedQuerySummary | null>(null);
  const [exporting, setExporting] = useState(false);

  const canManageQueries = caps().manageQueries || caps().manage;
  const loaded = useSavedQuery(editingSlug, editingSlug !== '');

  useEffect(() => {
    if (!loaded.data) return;
    setTitle(loaded.data.title);
    setIsPublic(loaded.data.public);
    setDefinition(loaded.data.definition ?? defaultDefinition());
  }, [loaded.data]);

  useEffect(() => {
    if (!bootstrap.data) return;
    if (!editingSlug && !loaded.data) {
      setDefinition((d) => (d.content_type ? d : { ...d, content_type: bootstrap.data!.contentTypes[0]?.id ?? 'post' }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bootstrap.data]);

  const schema = useMemo(
    () => ({
      fields: bootstrap.data?.fields ?? [],
      relationships: bootstrap.data?.relationships ?? [],
      taxonomies: bootstrap.data?.taxonomies ?? [],
      tokens: bootstrap.data?.tokens ?? {},
    }),
    [bootstrap.data],
  );

  if (bootstrap.isLoading) return <PageLoader />;

  const savedQueries = bootstrap.data?.savedQueries ?? [];
  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const readonly = !!loaded.data?.readonly;

  const startNew = () => {
    setEditingSlug('');
    setEditingId(null);
    setTitle('');
    setIsPublic(false);
    setDefinition(defaultDefinition(contentTypes[0]?.id ?? 'post'));
    setResults(null);
    setExplain(null);
    setExplainOpen(false);
  };

  const loadQuery = (q: SavedQuerySummary) => {
    setEditingSlug(q.slug);
    setEditingId(q.id);
    setTitle(q.title);
    setIsPublic(q.public);
    setResults(null);
    setExplain(null);
    setExplainOpen(false);
  };

  const onSave = async () => {
    if (!title.trim()) {
      toast.error('Name is required.');
      return;
    }
    const id = typeof editingId === 'number' ? editingId : undefined;
    const res = await saveQuery.mutateAsync({ id, title, public: isPublic, definition });
    toast.success(id ? 'Query updated.' : 'Query saved.');
    const newId = typeof res.id === 'number' ? res.id : id;
    setEditingId(newId ?? null);
    if (res.slug) setEditingSlug(res.slug);
    bootstrap.refetch();
    loaded.refetch();
  };

  const onDelete = async (q: SavedQuerySummary) => {
    await deleteQuery.mutateAsync(q.id);
    toast.success('Query deleted.');
    setConfirmDelete(null);
    if (q.slug === editingSlug) startNew();
    bootstrap.refetch();
  };

  const onClone = async (q: SavedQuerySummary) => {
    await cloneQuery.mutateAsync(q.id);
    toast.success('Query cloned.');
    bootstrap.refetch();
  };

  const onTest = async () => {
    setResults(null);
    setExplain(null);
    const res = await testQuery.mutateAsync({ query: definition });
    setResults(res.items ?? []);
    setTotal(res.total ?? 0);
  };

  const onExplain = async () => {
    const res = await explainQuery.mutateAsync({ query: definition });
    setExplain(res);
    setExplainOpen(true);
  };

  const copySnippet = async (value: string) => {
    await navigator.clipboard.writeText(value);
    toast.success('Snippet copied.');
  };

  const onExport = async (slug: string, format: 'csv' | 'json') => {
    setExporting(true);
    try {
      if (format === 'csv') {
        const blob = await apiDownload(`query/${encodeURIComponent(slug)}/export?format=csv&limit=500`);
        triggerDownload(blob, `${slug}-${new Date().toISOString().slice(0, 10)}.csv`);
      } else {
        const data = await apiGet<{ slug: string; title: string; total: number; items: unknown[] }>(`query/${encodeURIComponent(slug)}/export?format=json&limit=500`);
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        triggerDownload(blob, `${slug}.json`);
      }
      toast.success('Export downloaded.');
    } catch {
      toast.error('Export failed.');
    } finally {
      setExporting(false);
    }
  };

  const listColumns: Column<SavedQuerySummary>[] = [
    {
      key: 'name',
      header: 'Name',
      render: (q) => (
        <div>
          <strong className="text-ink">{q.title}</strong>
          <br />
          <code className="font-mono text-[11px] text-ink-faint">{q.slug}</code>
        </div>
      ),
    },
    { key: 'managed', header: 'Managed by', render: (q) => <NodeChip tone={q.managed_by === 'code' ? 'amber' : 'indigo'}>{q.managed_by}</NodeChip> },
    { key: 'usage', header: 'Usage', render: (q) => <span className="font-mono text-[12px] text-ink-soft">{q.usage ?? 0}</span> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (q) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="sm" onClick={() => loadQuery(q)}>Open</Button>
          <Button variant="ghost" size="sm" icon={<Copy className="h-3.5 w-3.5" />} onClick={() => onClone(q)} aria-label="Clone" />
          {q.managed_by === 'database' && canManageQueries && (
            <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => setConfirmDelete(q)} aria-label="Delete" />
          )}
        </div>
      ),
    },
  ];

  const resultColumns: Column<QueryRunItem>[] = [
    { key: 'id', header: 'ID', render: (r) => <span className="font-mono text-[12px] text-ink-soft">{r.id}</span> },
    { key: 'label', header: 'Label', render: (r) => <strong className="text-ink">{(r.label as string) ?? (r.title as string) ?? `#${r.id}`}</strong> },
    { key: 'type', header: 'Type', render: (r) => <NodeChip tone="neutral">{(r.object as string) ?? ''}</NodeChip> },
    {
      key: 'goto',
      header: '',
      align: 'right',
      render: () => <Button variant="ghost" size="sm" onClick={() => navigate('/inspector')}>Inspect</Button>,
    },
  ];

  const usageRows = loaded.data?.usage_rows ?? [];

  return (
    <div className="space-y-6">
      <div className="rise flex items-end justify-between gap-3">
        <div>
          <p className="eyebrow">Smart collections</p>
          <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Queries</h2>
          <p className="mt-1 text-[13px] text-ink-faint">Build a query once, then reuse it in Gutenberg, Bricks, Elementor or any builder adapter.</p>
        </div>
        {canManageQueries && <Button icon={<Plus className="h-4 w-4" />} onClick={startNew}>New query</Button>}
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div className="rise xl:col-span-2" style={{ animationDelay: '60ms' }}>
          <Card>
            <CardHeader title="Saved queries" desc={`${savedQueries.length} reusable collections`} />
            <CardBody className="p-0">
              <DataTable columns={listColumns} rows={savedQueries} rowKey={(q) => String(q.id)} emptyTitle="No saved queries" emptyDesc="Create one to start reusing it across builders." />
            </CardBody>
          </Card>
        </div>

        <div className="rise xl:col-span-3" style={{ animationDelay: '120ms' }}>
          <Card>
            <CardHeader
              title={editingSlug ? `Editing · ${editingSlug}` : 'Query builder'}
              desc={readonly ? 'This query is managed by code — clone it to create an editable copy.' : 'Nested AND/OR filters with typed operators and contextual tokens'}
            />
            <CardBody className="space-y-5">
              <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                  <Label>Name</Label>
                  <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Related game coverage" disabled={!canManageQueries || readonly} />
                </div>
                <div>
                  <Label>Content type</Label>
                  <Select value={definition.content_type} onChange={(e) => setDefinition({ ...definition, content_type: e.target.value })} disabled={readonly}>
                    {contentTypes.map((ct) => (
                      <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label} · {ct.kind ?? ''}</option>
                    ))}
                  </Select>
                </div>
                <div>
                  <Label>Display</Label>
                  <Select value={definition.display ?? 'list'} onChange={(e) => setDefinition({ ...definition, display: e.target.value })} disabled={readonly}>
                    <option value="list">List</option>
                    <option value="block">Block</option>
                    <option value="feed">Feed</option>
                    <option value="rest">REST only</option>
                  </Select>
                </div>
              </div>

              <div>
                <Label>Search</Label>
                <Input value={definition.search ?? ''} onChange={(e) => setDefinition({ ...definition, search: e.target.value })} placeholder="Optional full-text search" disabled={readonly} />
              </div>

              <QueryBuilder schema={schema} definition={definition} onChange={setDefinition} contentType={definition.content_type} />

              <div className="space-y-2">
                <Label>Exposed filters</Label>
                {(definition.exposed_filters ?? []).map((f, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <Select
                      value={f.field}
                      onChange={(e) => setDefinition({ ...definition, exposed_filters: (definition.exposed_filters ?? []).map((x, j) => (j === i ? { ...x, field: e.target.value } : x)) })}
                      className="h-8 max-w-[260px] text-[12.5px]"
                      disabled={readonly}
                    >
                      {schema.fields.map((fd) => <option key={fd.id} value={fd.id}>{fd.label}</option>)}
                    </Select>
                    <Input
                      value={f.label ?? ''}
                      onChange={(e) => setDefinition({ ...definition, exposed_filters: (definition.exposed_filters ?? []).map((x, j) => (j === i ? { ...x, label: e.target.value } : x)) })}
                      placeholder="Label"
                      className="h-8 max-w-[180px] text-[12.5px]"
                      disabled={readonly}
                    />
                    <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => setDefinition({ ...definition, exposed_filters: (definition.exposed_filters ?? []).filter((_, j) => j !== i) })} disabled={readonly} className="h-8 w-8 p-0" aria-label="Remove filter" />
                  </div>
                ))}
                {!readonly && (
                  <Button variant="secondary" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={() => setDefinition({ ...definition, exposed_filters: [...(definition.exposed_filters ?? []), { field: schema.fields[0]?.id ?? '', label: '', input: 'text' }] })} disabled={!schema.fields.length}>Add exposed filter</Button>
                )}
                <p className="text-[12px] text-ink-faint">Exposed filters render as a form via <code className="font-mono">[cgm_view id="{editingSlug || 'slug'}" filters="1"]</code>.</p>
              </div>

              <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                  <Label>Limit</Label>
                  <Input type="number" min={1} value={definition.limit} disabled={readonly} onChange={(e) => setDefinition({ ...definition, limit: Math.max(1, Number(e.target.value) || 12) })} />
                </div>
                <div>
                  <Label>Offset</Label>
                  <Input type="number" min={0} value={definition.offset} disabled={readonly} onChange={(e) => setDefinition({ ...definition, offset: Math.max(0, Number(e.target.value) || 0) })} />
                </div>
                <div>
                  <Label>Cache TTL (s)</Label>
                  <Input type="number" min={15} value={definition.cache_ttl} disabled={readonly} onChange={(e) => setDefinition({ ...definition, cache_ttl: Math.max(15, Number(e.target.value) || 120) })} />
                </div>
                <div className="flex flex-col justify-end">
                  <ToggleRow label="Public REST" checked={isPublic} onChange={setIsPublic} disabled={!canManageQueries || readonly} />
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                <Button variant="secondary" icon={<Play className="h-4 w-4" />} onClick={onTest} loading={testQuery.isPending}>Test query</Button>
                <Button variant="outline" icon={<Braces className="h-4 w-4" />} onClick={onExplain} loading={explainQuery.isPending}>Explain plan</Button>
                {canManageQueries && !readonly && (
                  <Button className="ml-auto" icon={<Save className="h-4 w-4" />} onClick={onSave} loading={saveQuery.isPending}>
                    {editingId != null ? 'Update query' : 'Save query'}
                  </Button>
                )}
              </div>
            </CardBody>
          </Card>
        </div>
      </div>

      {results !== null && (
        <Card className="rise" style={{ animationDelay: '180ms' }}>
          <CardHeader title={`Results · ${total} total`} desc="Live preview from the query engine" />
          <CardBody className="p-0">
            <DataTable columns={resultColumns} rows={results} rowKey={(r) => String(r.id)} emptyTitle="No results" emptyDesc="Adjust the filters and try again." />
          </CardBody>
        </Card>
      )}

      {explainOpen && explain && (
        <Card className="rise" style={{ animationDelay: '180ms' }}>
          <CardHeader title="Explain plan" desc="Compiled query plan, dependencies and provider" />
          <CardBody>
            <pre className="max-h-96 overflow-auto rounded-md border border-line bg-surface-2/50 p-4 font-mono text-[12px] text-ink-soft">{JSON.stringify(explain, null, 2)}</pre>
          </CardBody>
        </Card>
      )}

      {editingSlug && (
        <Card className="rise" style={{ animationDelay: '220ms' }}>
          <CardHeader title="Usage & export" desc="Where this collection is used, and how to consume it elsewhere" />
          <CardBody className="space-y-4">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div>
                <p className="eyebrow mb-2">Copy snippet</p>
                <div className="space-y-2">
                  {[
                    { icon: Link2, label: 'Shortcode', value: `[cgm_query id="${editingSlug}"]` },
                    { icon: Link2, label: 'View shortcode', value: `[cgm_view id="${editingSlug}"]` },
                    { icon: Link2, label: 'View + exposed filters', value: `[cgm_view id="${editingSlug}" filters="1"]` },
                    { icon: Braces, label: 'PHP', value: `cgm_query( '${editingSlug}' )` },
                    { icon: Braces, label: 'Bricks query type', value: `CGM: ${title || editingSlug}` },
                  ].map((s) => (
                    <div key={s.label} className="flex items-center gap-2 rounded-md border border-line bg-surface-2/40 px-3 py-2">
                      <s.icon className="h-3.5 w-3.5 shrink-0 text-indigo-bright" />
                      <span className="min-w-0 flex-1 truncate font-mono text-[12px] text-ink-soft">{s.value}</span>
                      <Button variant="ghost" size="sm" icon={<Copy className="h-3.5 w-3.5" />} onClick={() => copySnippet(s.value)} aria-label={`Copy ${s.label}`} />
                    </div>
                  ))}
                </div>
              </div>

              <div>
                <p className="eyebrow mb-2">Export</p>
                <div className="flex gap-2">
                  <Button variant="outline" icon={<Download className="h-4 w-4" />} onClick={() => onExport(editingSlug, 'csv')} loading={exporting}>CSV</Button>
                  <Button variant="outline" icon={<FileJson className="h-4 w-4" />} onClick={() => onExport(editingSlug, 'json')} loading={exporting}>JSON</Button>
                </div>
                <p className="mt-2 text-[12px] text-ink-faint">Exports up to 500 rows from the collection.</p>
              </div>
            </div>

            <div>
              <p className="eyebrow mb-2">Where used ({usageRows.length})</p>
              {usageRows.length ? (
                <div className="space-y-1">
                  {usageRows.map((u, i) => (
                    <div key={i} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                      <NodeChip tone="neutral">{u.consumer}</NodeChip>
                      <code className="min-w-0 flex-1 truncate font-mono text-[12px] text-ink-faint">{u.location || '—'}</code>
                      <span className="font-mono text-[11px] text-ink-faint">×{u.count}</span>
                      <span className="font-mono text-[11px] text-ink-faint">{u.last_used ? new Date(u.last_used).toLocaleDateString() : ''}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-[13px] text-ink-faint">Not yet executed anywhere. Run it in a template, block, or feed to track usage.</p>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {confirmDelete && (
        <div className="fixed inset-0 z-[100000] flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-ink/40 backdrop-blur-[2px]" onClick={() => setConfirmDelete(null)} />
          <Card className="relative w-full max-w-md">
            <CardHeader title="Delete query" desc={`"${confirmDelete.title}" will be permanently deleted.`} />
            <CardBody className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setConfirmDelete(null)}>Cancel</Button>
              <Button variant="danger" onClick={() => onDelete(confirmDelete)} loading={deleteQuery.isPending}>Delete</Button>
            </CardBody>
          </Card>
        </div>
      )}
    </div>
  );
}
