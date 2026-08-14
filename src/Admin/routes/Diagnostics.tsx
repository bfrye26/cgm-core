import { useMemo, useState } from 'react';
import { Stethoscope, Copy, Database } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Stat, NodeChip, PageLoader } from '@/components/ui';
import { useBootstrap, useIndexes, useRebuildIndexes } from '@/lib/hooks';

export function DiagnosticsPage() {
  const bootstrap = useBootstrap();
  const indexes = useIndexes();
  const rebuildIndexes = useRebuildIndexes();
  const [copied, setCopied] = useState(false);
  const data = bootstrap.data;

  const report = useMemo(
    () =>
      JSON.stringify(
        {
          version: data?.version,
          api: data?.api,
          schema: data?.schema,
          providers: data?.providers,
          builders: data?.builders,
          queryProviders: data?.queryProviders,
          multisite: data?.multisite,
        },
        null,
        2,
      ),
    [data],
  );

  const copy = async () => {
    await navigator.clipboard.writeText(report);
    setCopied(true);
    toast.success('Diagnostics report copied.');
    setTimeout(() => setCopied(false), 2000);
  };

  if (bootstrap.isLoading) return <PageLoader />;

  const providers = data?.providers ?? [];
  const builders = data?.builders ?? [];
  const queryProviders = data?.queryProviders ?? [];
  const multisite = data?.multisite ?? {};

  return (
    <div className="space-y-6">
      <div className="rise flex items-end justify-between gap-3">
        <div>
          <p className="eyebrow">System health</p>
          <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Diagnostics</h2>
          <p className="mt-1 text-[13px] text-ink-faint">Versioned contracts, provider compatibility and builder adapters.</p>
        </div>
        <Button variant="outline" size="sm" icon={<Copy className="h-3.5 w-3.5" />} onClick={copy}>{copied ? 'Copied' : 'Copy report'}</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="Core" value={data?.version ?? '—'} icon={<Stethoscope className="h-4 w-4" />} tone="indigo" delay={0} />
        <Stat label="Core API" value={data?.api?.core ?? '—'} tone="amber" delay={60} />
        <Stat label="Query API" value={data?.api?.query ?? '—'} tone="neutral" delay={120} />
        <Stat label="Schema" value={data?.schema ?? '—'} tone="neutral" delay={180} />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Card className="rise" style={{ animationDelay: '220ms' }}>
          <CardHeader title="Providers" desc="Status and compatibility" />
          <CardBody className="space-y-1">
            {providers.map((p) => {
              const bad = p.status === 'incompatible' || p.status === 'legacy-registration';
              return (
                <div key={p.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                  <NodeChip tone={bad ? 'rust' : 'indigo'}>{p.label ?? p.id}</NodeChip>
                  <div className="min-w-0 flex-1">
                    <code className="font-mono text-[12px] text-ink-faint">{p.id}</code>
                  </div>
                  <span className="font-mono text-[11px] text-ink-faint">{p.version ? `v${p.version}` : ''}</span>
                  <NodeChip tone={bad ? 'rust' : 'pine'}>{p.status ?? 'ready'}</NodeChip>
                </div>
              );
            })}
          </CardBody>
        </Card>

        <Card className="rise" style={{ animationDelay: '280ms' }}>
          <CardHeader title="Builders" desc="Adapter integration level" />
          <CardBody className="space-y-1">
            {builders.map((b) => (
              <div key={b.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                <NodeChip tone={b.detected ? 'pine' : 'neutral'}>{b.label ?? b.id}</NodeChip>
                <div className="min-w-0 flex-1">
                  <p className="font-mono text-[12px] text-ink-faint">{b.integration_level ?? ''}</p>
                </div>
                <span className="text-[12px] text-ink-faint">{(b.capabilities ?? []).join(', ')}</span>
              </div>
            ))}
          </CardBody>
        </Card>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Card className="rise" style={{ animationDelay: '320ms' }}>
          <CardHeader title="Query providers" desc="Registered provider per content type" />
          <CardBody>
            <pre className="max-h-64 overflow-auto rounded-md border border-line bg-surface-2/50 p-4 font-mono text-[12px] text-ink-soft">{JSON.stringify(queryProviders, null, 2)}</pre>
          </CardBody>
        </Card>

        <Card className="rise" style={{ animationDelay: '360ms' }}>
          <CardHeader title="Multisite policy" desc="Local-first, optional network defaults" />
          <CardBody>
            <pre className="max-h-64 overflow-auto rounded-md border border-line bg-surface-2/50 p-4 font-mono text-[12px] text-ink-soft">{JSON.stringify(multisite, null, 2)}</pre>
          </CardBody>
        </Card>
      </div>

      <Card className="rise" style={{ animationDelay: '380ms' }}>
        <CardHeader
          title="Search indexes"
          desc="Index definitions registered by search providers"
            action={
              <Button variant="outline" size="sm" icon={<Database className="h-3.5 w-3.5" />} onClick={() => { rebuildIndexes.mutate(undefined); toast.success('Rebuild queued.'); }} loading={rebuildIndexes.isPending}>Rebuild all</Button>
            }
        />
        <CardBody className="space-y-1">
          {(indexes.data?.items ?? []).length === 0 ? (
            <p className="text-[13px] text-ink-faint">No search indexes registered. Search providers register their indexes via <code className="font-mono">cgm_register_index()</code>.</p>
          ) : (
            (indexes.data?.items ?? []).map((idx) => (
              <div key={idx.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                <NodeChip tone="indigo">{idx.label ?? idx.id}</NodeChip>
                <code className="min-w-0 flex-1 truncate font-mono text-[12px] text-ink-faint">{idx.id}</code>
                <span className="font-mono text-[11px] text-ink-faint">{(idx.content_types ?? []).join(', ') || '*'}</span>
              </div>
            ))
          )}
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '400ms' }}>
        <CardHeader title="System report" desc="Versioned contracts and registry snapshot" />
        <CardBody>
          <pre className="max-h-96 overflow-auto rounded-md border border-line bg-surface-2/50 p-4 font-mono text-[12px] text-ink-soft">{report}</pre>
        </CardBody>
      </Card>
    </div>
  );
}
