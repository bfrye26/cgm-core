import { Card, CardHeader, CardBody, PageLoader, Notice, NodeChip } from '@/components/ui';
import { MetricGrid } from '@/components/blocks';
import { useBootstrap } from '@/lib/hooks';

export function SetupPage() {
  const bootstrap = useBootstrap();
  if (bootstrap.isLoading) return <PageLoader />;

  const counts = bootstrap.data?.counts;
  const providers = bootstrap.data?.providers ?? [];
  const builders = bootstrap.data?.builders ?? [];
  const incompatible = providers.filter((p) => p.status === 'incompatible' || p.status === 'legacy-registration');

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Discovery</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Setup</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Core discovers WordPress and connected providers automatically. This is what is ready before you build queries or relationships.</p>
      </div>

      <MetricGrid
        metrics={[
          { label: 'Content types', value: counts?.content_types ?? 0, tone: 'indigo' },
          { label: 'Queryable fields', value: counts?.fields ?? 0, tone: 'amber' },
          { label: 'Relationships', value: counts?.relationships ?? 0, tone: 'neutral' },
          { label: 'Saved queries', value: counts?.queries ?? 0, tone: 'indigo' },
        ]}
      />

      <Card className="rise" style={{ animationDelay: '120ms' }}>
        <CardHeader title="Builder connections" desc="Where Core is the data source and the builder owns presentation" />
        <CardBody>
          <div className="space-y-2">
            {builders.map((b) => (
              <div key={b.id} className="flex items-center gap-3 rounded-md border border-line px-4 py-3">
                <NodeChip tone={b.detected ? 'pine' : 'neutral'}>{b.label ?? b.id}</NodeChip>
                <div className="min-w-0 flex-1">
                  <p className="text-[13px] font-medium text-ink">{b.detected ? 'Detected' : 'Not detected'}</p>
                  <p className="font-mono text-[11px] text-ink-faint">{b.integration_level ?? 'bridge'}</p>
                </div>
                <span className="text-[12px] text-ink-faint">{(b.capabilities ?? []).join(', ')}</span>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '180ms' }}>
        <CardHeader title="Provider health" desc="Writes fail closed until a provider's bridge is compatible" />
        <CardBody>
          {incompatible.length ? (
            <Notice tone="gold">Some providers report an incompatible or incomplete bridge. Core will fail closed for writes until the matching provider is updated.</Notice>
          ) : (
            <Notice tone="pine">No registered provider compatibility problems were detected.</Notice>
          )}
          <div className="mt-4 space-y-1">
            {providers.map((p) => {
              const bad = p.status === 'incompatible' || p.status === 'legacy-registration';
              return (
                <div key={p.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                  <NodeChip tone={bad ? 'rust' : 'indigo'}>{p.label ?? p.id}</NodeChip>
                  <div className="min-w-0 flex-1">
                    <code className="font-mono text-[12px] text-ink-faint">{p.id}</code>
                    {p.capabilities && p.capabilities.length > 0 && <p className="truncate text-[12px] text-ink-faint">{p.capabilities.join(', ')}</p>}
                  </div>
                  <span className="font-mono text-[11px] text-ink-faint">{p.version ? `v${p.version}` : ''}</span>
                  <NodeChip tone={bad ? 'rust' : 'pine'}>{p.status ?? 'ready'}</NodeChip>
                </div>
              );
            })}
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
