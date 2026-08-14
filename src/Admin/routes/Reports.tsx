import { useState } from 'react';
import { BarChart3, Play } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Select, Label, PageLoader, Notice, EmptyState } from '@/components/ui';
import { useBootstrap, useAggregate, useSavedQuery, defaultDefinition } from '@/lib/hooks';

export function ReportsPage() {
  const bootstrap = useBootstrap();
  const aggregate = useAggregate();

  const [contentType, setContentType] = useState('');
  const [savedSlug, setSavedSlug] = useState('');
  const [groupBy, setGroupBy] = useState('');
  const [fn, setFn] = useState('COUNT');
  const [aggField, setAggField] = useState('');
  const [result, setResult] = useState<{ rows: { label?: string; total?: number; [k: string]: unknown }[]; function: string } | null>(null);

  const savedQuery = useSavedQuery(savedSlug, savedSlug !== '');

  if (bootstrap.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const savedQueries = bootstrap.data?.savedQueries ?? [];
  const fields = bootstrap.data?.fields ?? [];
  const taxonomies = bootstrap.data?.taxonomies ?? [];
  const relationships = bootstrap.data?.relationships ?? [];

  const groupOptions = [
    ...fields.map((f) => ({ value: f.id, label: `Field · ${f.label}` })),
    ...taxonomies.map((t) => ({ value: `taxonomy.${t.id}`, label: `Taxonomy · ${t.label}` })),
    ...relationships.map((r) => ({ value: `relationship.${r.id}`, label: `Relationship · ${r.label}` })),
  ];

  const numericFields = fields.filter((f) => ['integer', 'number'].includes(f.type));

  const effectiveContentType = savedSlug && savedQuery.data ? savedQuery.data.definition.content_type : contentType || contentTypes[0]?.id || 'post';

  const run = async () => {
    if (!groupBy) {
      toast.error('Choose a group-by field.');
      return;
    }
    if (fn !== 'COUNT' && !aggField) {
      toast.error('Choose a numeric field to aggregate.');
      return;
    }
    const base = savedQuery.data ? savedQuery.data.definition : defaultDefinition(effectiveContentType);
    const query = { ...base, aggregate: { group_by: groupBy, function: fn, limit: 50, field: fn !== 'COUNT' ? aggField : undefined } };
    const res = await aggregate.mutateAsync({ query });
    setResult(res as { rows: { label?: string; total?: number }[]; function: string });
  };

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Aggregation</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Reports</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Count, sum and average content grouped by a field or taxonomy — coverage per game, output per author, volume per tag.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="Build a report" desc="Group a content type or saved query by any field or taxonomy" />
        <CardBody className="space-y-4">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <Label>Source</Label>
              <Select value={savedSlug ? `query:${savedSlug}` : contentType} onChange={(e) => {
                const v = e.target.value;
                if (v.startsWith('query:')) { setSavedSlug(v.slice(6)); setContentType(''); }
                else { setSavedSlug(''); setContentType(v); }
              }}>
                <optgroup label="Content types">
                  {contentTypes.map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
                </optgroup>
                <optgroup label="Saved queries">
                  {savedQueries.map((q) => <option key={q.slug} value={`query:${q.slug}`}>{q.title}</option>)}
                </optgroup>
              </Select>
            </div>
            <div>
              <Label>Group by</Label>
              <Select value={groupBy} onChange={(e) => setGroupBy(e.target.value)}>
                <option value="">— choose —</option>
                {groupOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
              </Select>
            </div>
            <div>
              <Label>Function</Label>
              <Select value={fn} onChange={(e) => setFn(e.target.value)}>
                <option value="COUNT">Count</option>
                <option value="SUM">Sum</option>
                <option value="AVG">Average</option>
                <option value="MIN">Min</option>
                <option value="MAX">Max</option>
              </Select>
            </div>
            {fn !== 'COUNT' && (
              <div>
                <Label>Aggregate field</Label>
                <Select value={aggField} onChange={(e) => setAggField(e.target.value)}>
                  <option value="">— choose numeric field —</option>
                  {numericFields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                </Select>
              </div>
            )}
            <div className="flex items-end">
              <Button icon={<Play className="h-4 w-4" />} onClick={run} loading={aggregate.isPending}>Run report</Button>
            </div>
          </div>
        </CardBody>
      </Card>

      {aggregate.isError && <Notice tone="rust">The report could not run. Check the group-by field.</Notice>}

      {result && (
        <Card className="rise" style={{ animationDelay: '120ms' }}>
          <CardHeader title={`Result · ${result.function}`} desc="Grouped values, highest first" action={<BarChart3 className="h-4 w-4 text-indigo-bright" />} />
          <CardBody>
            {result.rows.length === 0 ? (
              <EmptyState icon={<BarChart3 className="h-5 w-5" />} title="No data" desc="No rows matched this grouping." />
            ) : (
              <div className="space-y-1.5">
                {result.rows.map((row, i) => {
                  const label = String(row.label ?? '—');
                  const total = Number(row.total ?? 0);
                  const max = Math.max(1, ...result.rows.map((r) => Number(r.total ?? 0)));
                  return (
                    <div key={i} className="flex items-center gap-3">
                      <span className="w-44 truncate text-[13px] font-medium text-ink">{label}</span>
                      <div className="h-5 flex-1 overflow-hidden rounded bg-surface-2">
                        <div className="h-full rounded bg-indigo-bright/80" style={{ width: `${Math.round((total / max) * 100)}%` }} />
                      </div>
                      <span className="w-16 text-right font-mono text-[12px] text-ink-soft">{total}</span>
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
