import { useState } from 'react';
import { Search } from 'lucide-react';
import { Card, CardHeader, CardBody, Button, Input, Select, Label, NodeChip, PageLoader, Notice, EmptyState } from '@/components/ui';
import { useBootstrap, useSearchObjects, useObject } from '@/lib/hooks';

export function InspectorPage() {
  const bootstrap = useBootstrap();
  const [contentType, setContentType] = useState('post');
  const [search, setSearch] = useState('');
  const [objectId, setObjectId] = useState(0);

  const searchQuery = useSearchObjects(contentType, search);
  const objectQuery = useObject(contentType, objectId);

  if (bootstrap.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Normalized view</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Inspector</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Inspect the Core view of any registered object without changing its source data.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="Find an object" desc="Search across any registered content type" />
        <CardBody>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-[200px_1fr_auto]">
            <div>
              <Label>Content type</Label>
              <Select value={contentType} onChange={(e) => { setContentType(e.target.value); setObjectId(0); }}>
                {contentTypes.map((ct) => (
                  <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Search</Label>
              <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Type to search…" />
            </div>
            <div className="flex items-end">
              <Button variant="secondary" icon={<Search className="h-4 w-4" />} disabled={!search.length}>Search</Button>
            </div>
          </div>

          {search.length > 0 && (
            <div className="mt-4 space-y-1">
              {(searchQuery.data?.items ?? []).map((item) => (
                <button
                  key={item.id}
                  onClick={() => setObjectId(Number(item.id))}
                  className="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition hover:bg-indigo-soft/40"
                >
                  <NodeChip tone="indigo">#{item.id}</NodeChip>
                  <span className="text-[13.5px] font-medium text-ink">{String(item.label ?? item.title ?? `#${item.id}`)}</span>
                </button>
              ))}
              {searchQuery.isSuccess && (searchQuery.data?.items ?? []).length === 0 && (
                <p className="px-2 py-3 text-[13px] text-ink-faint">No matches.</p>
              )}
            </div>
          )}
        </CardBody>
      </Card>

      {objectId > 0 && (
        <Card className="rise" style={{ animationDelay: '120ms' }}>
          <CardHeader title={`Object · ${contentType}:${objectId}`} desc={objectQuery.isFetching ? 'Loading…' : 'Serialized Core view'} />
          <CardBody>
            {objectQuery.isError ? (
              <Notice tone="rust">Object not found or not readable.</Notice>
            ) : objectQuery.isSuccess && objectQuery.data ? (
              <pre className="max-h-96 overflow-auto rounded-md border border-line bg-surface-2/50 p-4 font-mono text-[12px] text-ink-soft">{JSON.stringify(objectQuery.data, null, 2)}</pre>
            ) : (
              <EmptyState icon={<Search className="h-5 w-5" />} title="Select an object" desc="Choose a search result above to inspect its normalized view." />
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}
