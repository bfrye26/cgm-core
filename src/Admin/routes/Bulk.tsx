import { useState } from 'react';
import { Layers, Play, Search } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Select, Label, PageLoader, Notice, EmptyState } from '@/components/ui';
import { ActionsEditor } from '@/components/actions-editor';
import { useBootstrap, useRules, useBulkPreview, useBulkRun, defaultDefinition, type RuleAction, type QueryDefinition } from '@/lib/hooks';

export function BulkPage() {
  const bootstrap = useBootstrap();
  const rulesQuery = useRules();
  const preview = useBulkPreview();
  const run = useBulkRun();

  const [source, setSource] = useState('');
  const [actions, setActions] = useState<RuleAction[]>([]);
  const [previewResult, setPreviewResult] = useState<{ count: number; sample: { id: number; type: string; label: string }[] } | null>(null);
  const [runResult, setRunResult] = useState<{ processed: number; succeeded: number; failed: number } | null>(null);

  if (bootstrap.isLoading || rulesQuery.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const savedQueries = bootstrap.data?.savedQueries ?? [];
  const relationships = bootstrap.data?.relationships ?? [];
  const actionDefs = rulesQuery.data?.actions ?? [];

  const queryParam = (): string | QueryDefinition => {
    if (source.startsWith('query:')) return source.slice(6);
    return defaultDefinition(source || contentTypes[0]?.id || 'post');
  };

  const onPreview = async () => {
    setRunResult(null);
    if (!actions.length) { toast.error('Add at least one action.'); return; }
    const res = await preview.mutateAsync({ query: queryParam(), actions });
    setPreviewResult(res);
  };

  const onRun = async () => {
    if (!actions.length) { toast.error('Add at least one action.'); return; }
    const res = await run.mutateAsync({ query: queryParam(), actions });
    setRunResult(res);
    toast.success(`Bulk operation complete: ${res.succeeded} succeeded, ${res.failed} failed.`);
  };

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Views bulk operations</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Bulk operations</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Run an action against every object in a saved query or content type — set terms, statuses, meta, relationships, and more, in one pass.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="1 · Select the result set" desc="A saved query or a whole content type" />
        <CardBody>
          <div className="max-w-md">
            <Label>Source</Label>
            <Select value={source || contentTypes[0]?.id || ''} onChange={(e) => setSource(e.target.value)}>
              <optgroup label="Content types">
                {contentTypes.map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
              </optgroup>
              <optgroup label="Saved queries">
                {savedQueries.map((q) => <option key={q.slug} value={`query:${q.slug}`}>{q.title}</option>)}
              </optgroup>
            </Select>
          </div>
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '120ms' }}>
        <CardHeader title="2 · Choose actions" desc="Applied to each object in the result set" />
        <CardBody>
          <ActionsEditor actions={actions} onChange={setActions} relationships={relationships} actionDefs={actionDefs} />
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '180ms' }}>
        <CardHeader title="3 · Preview and run" desc="Preview the object count first, then apply" />
        <CardBody>
          <div className="flex gap-2">
            <Button variant="outline" icon={<Search className="h-4 w-4" />} onClick={onPreview} loading={preview.isPending}>Preview</Button>
            <Button icon={<Play className="h-4 w-4" />} onClick={onRun} loading={run.isPending} disabled={!previewResult}>Run</Button>
          </div>

          {previewResult && (
            <Notice tone="pine" className="mt-4">
              {previewResult.count} object{previewResult.count === 1 ? '' : 's'} match — sample: {previewResult.sample.map((s) => s.label || `#${s.id}`).join(', ') || '—'}
            </Notice>
          )}

          {runResult && (
            <Notice tone="pine" className="mt-4">
              Processed {runResult.processed} · {runResult.succeeded} succeeded · {runResult.failed} failed
            </Notice>
          )}

          {!previewResult && !runResult && (
            <p className="mt-4 text-[13px] text-ink-faint">Preview to see how many objects will be affected before running.</p>
          )}
        </CardBody>
      </Card>

      {contentTypes.length === 0 && savedQueries.length === 0 && (
        <EmptyState icon={<Layers className="h-5 w-5" />} title="Nothing to operate on" desc="Define content or a saved query first." />
      )}
    </div>
  );
}
