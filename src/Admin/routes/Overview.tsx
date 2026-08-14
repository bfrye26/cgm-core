import { useNavigate } from 'react-router-dom';
import { Box, Boxes, GitBranch, Rows3, Puzzle, LayoutGrid, ArrowRight, Activity, Gauge, Bell, X } from 'lucide-react';
import { Card, CardHeader, CardBody, Stat, NodeChip, PageLoader, Button } from '@/components/ui';
import { useBootstrap, useActivity, usePerformance, useNotifications, useDismissNotification } from '@/lib/hooks';
import { formatNumber } from '@/lib/utils';

export function OverviewPage() {
  const navigate = useNavigate();
  const bootstrap = useBootstrap();
  const activity = useActivity(10);
  const performance = usePerformance();
  const notifications = useNotifications();
  const dismiss = useDismissNotification();

  if (bootstrap.isLoading) return <PageLoader />;

  const counts = bootstrap.data?.counts;
  const providers = bootstrap.data?.providers ?? [];
  const builders = bootstrap.data?.builders ?? [];
  const activityRows = activity.data?.activity ?? [];
  const perfRows = performance.data?.queries ?? [];
  const notificationRows = notifications.data?.items ?? [];

  const steps = [
    { title: 'Connect builders', description: 'See which page builders are detected and what each can consume from Core.', actionLabel: 'Open Setup', onAction: () => navigate('/setup') },
    { title: 'Define a relationship', description: 'Model a reusable entity reference when WordPress has no native source of truth.', actionLabel: 'Open Relationships', onAction: () => navigate('/relationships') },
    { title: 'Build a query', description: 'Build once, then reuse it in Gutenberg, Bricks or Elementor.', actionLabel: 'Open Queries', onAction: () => navigate('/queries') },
  ];

  return (
    <div className="space-y-6">
      <div className="rise flex items-end justify-between gap-3">
        <div>
          <p className="eyebrow">Control room</p>
          <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Overview</h2>
          <p className="mt-1 text-[13px] text-ink-faint">One shared language for content, fields, relationships, queries and builders — domain plugins keep their own data.</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => navigate('/setup')}>Review setup</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Stat label="Providers" value={counts?.providers} icon={<Puzzle className="h-4 w-4" />} tone="indigo" delay={0} hint="Connected plugins and data sources" />
        <Stat label="Content types" value={counts?.content_types} icon={<Box className="h-4 w-4" />} tone="amber" delay={60} hint="Discovered object models" />
        <Stat label="Fields" value={counts?.fields} icon={<Boxes className="h-4 w-4" />} tone="neutral" delay={120} hint="Queryable + editable fields" />
        <Stat label="Relationships" value={counts?.relationships} icon={<GitBranch className="h-4 w-4" />} tone="indigo" delay={180} hint="Forward & reverse references" />
        <Stat label="Saved queries" value={counts?.queries} icon={<Rows3 className="h-4 w-4" />} tone="amber" delay={240} hint="Reusable Views-style queries" />
        <Stat label="Builders" value={counts?.builders} icon={<LayoutGrid className="h-4 w-4" />} tone="neutral" delay={300} hint="Editor & builder adapters" />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div className="rise xl:col-span-3" style={{ animationDelay: '320ms' }}>
          <Card>
            <CardHeader title="Get started" desc="The shortest path from raw registries to something you can reuse" />
            <CardBody>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {steps.map((s) => (
                  <div key={s.title} className="flex flex-col rounded-lg border border-line bg-surface-2/40 p-4">
                    <p className="font-display text-[14px] font-semibold text-ink">{s.title}</p>
                    <p className="mt-1 flex-1 text-[12.5px] text-ink-faint">{s.description}</p>
                    <button onClick={s.onAction} className="mt-3 inline-flex items-center gap-1 text-[13px] font-medium text-indigo-ink transition hover:text-indigo-bright">
                      {s.actionLabel} <ArrowRight className="h-3.5 w-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>

        <div className="rise xl:col-span-2" style={{ animationDelay: '380ms' }}>
          <Card>
            <CardHeader title="Builder connections" desc="What each adapter can consume from Core" />
            <CardBody>
              <div className="space-y-3">
                {builders.map((b) => (
                  <div key={b.id} className="flex items-start gap-3">
                    <NodeChip tone={b.detected ? 'pine' : 'neutral'} className="shrink-0">{b.label ?? b.id}</NodeChip>
                    <div className="min-w-0">
                      <p className="text-[13px] font-medium text-ink">{b.detected ? 'Detected' : 'Not detected'}</p>
                      <p className="truncate font-mono text-[11px] text-ink-faint">{(b.capabilities ?? []).join(' · ')}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>
      </div>

      <Card className="rise" style={{ animationDelay: '420ms' }}>
        <CardHeader title="Providers" desc="Compatibility report across the CGM suite" />
        <CardBody className="space-y-1">
          {providers.map((p) => {
            const bad = p.status === 'incompatible' || p.status === 'legacy-registration';
            return (
              <div key={p.id} className="flex items-center gap-3 rounded-md px-2 py-2.5 transition hover:bg-surface-2/60">
                <NodeChip tone={bad ? 'rust' : 'indigo'}>{p.label ?? p.id}</NodeChip>
                <div className="min-w-0 flex-1">
                  <code className="font-mono text-[12px] text-ink-faint">{p.id}</code>
                  {p.capabilities && p.capabilities.length > 0 && (
                    <p className="truncate text-[12px] text-ink-faint">{p.capabilities.join(', ')}</p>
                  )}
                </div>
                <span className="font-mono text-[11px] text-ink-faint">{p.version ? `v${p.version}` : ''}</span>
                <NodeChip tone={bad ? 'rust' : 'pine'}>{p.status ?? 'ready'}</NodeChip>
              </div>
            );
          })}
          {providers.length === 0 && <p className="px-2 py-3 text-[13px] text-ink-faint">No providers registered. {formatNumber(0)}</p>}
        </CardBody>
      </Card>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Card className="rise" style={{ animationDelay: '460ms' }}>
          <CardHeader title="Recent activity" desc="Cross-plugin events flowing through the Core event bus" action={<Activity className="h-4 w-4 text-ink-faint" />} />
          <CardBody>
            {activityRows.length ? (
              <div className="space-y-3">
                {activityRows.map((entry, i) => (
                  <div key={i} className="flex items-start gap-3">
                    <NodeChip tone={entry.event.includes('cascade') || entry.event.includes('deleted') ? 'rust' : 'neutral'} className="shrink-0">{entry.event.replace(/\./g, ' ')}</NodeChip>
                    <div className="min-w-0">
                      <p className="truncate text-[13px] font-medium text-ink">{entry.summary}</p>
                      <p className="font-mono text-[11px] text-ink-faint">{new Date(entry.occurred_at).toLocaleString()}</p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="px-2 py-3 text-[13px] text-ink-faint">No activity yet. Relationship and content changes will appear here.</p>
            )}
          </CardBody>
        </Card>

        <Card className="rise" style={{ animationDelay: '500ms' }}>
          <CardHeader title="Query performance" desc="Most-executed saved queries and their cache behaviour" action={<Gauge className="h-4 w-4 text-ink-faint" />} />
          <CardBody>
            {perfRows.length ? (
              <div className="space-y-2">
                {perfRows.slice(0, 10).map((q) => {
                  const hitRate = q.count ? Math.round((q.cache_hits / q.count) * 100) : 0;
                  const avg = q.count ? (q.total_ms / q.count).toFixed(1) : '0';
                  return (
                    <div key={q.slug} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                      <NodeChip tone="indigo" className="min-w-0 flex-1 justify-start">{q.slug}</NodeChip>
                      <span className="font-mono text-[11px] text-ink-faint">×{q.count}</span>
                      <span className="font-mono text-[11px] text-ink-faint">avg {avg}ms</span>
                      <NodeChip tone={hitRate > 50 ? 'pine' : 'gold'}>{hitRate}% cache</NodeChip>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="px-2 py-3 text-[13px] text-ink-faint">No saved queries have run yet. Performance appears here after real traffic.</p>
            )}
          </CardBody>
        </Card>
      </div>

      {notificationRows.length > 0 && (
        <Card className="rise" style={{ animationDelay: '540ms' }}>
          <CardHeader title="Notifications" desc="Dismissable notices from across the suite" action={<Bell className="h-4 w-4 text-amber-bright" />} />
          <CardBody className="space-y-1">
            {notificationRows.map((n) => (
              <div key={n.id} className="flex items-start gap-3 rounded-md px-2 py-2.5 transition hover:bg-surface-2/60">
                <NodeChip tone={n.type === 'warning' ? 'gold' : n.type === 'error' ? 'rust' : 'indigo'} className="shrink-0">{n.type}</NodeChip>
                <div className="min-w-0 flex-1">
                  <p className="text-[13.5px] font-medium text-ink">{n.title}</p>
                  <p className="text-[12.5px] text-ink-faint">{n.message}</p>
                </div>
                <Button variant="ghost" size="sm" icon={<X className="h-3.5 w-3.5" />} onClick={() => dismiss.mutate(n.id)} aria-label="Dismiss" />
              </div>
            ))}
          </CardBody>
        </Card>
      )}
    </div>
  );
}
