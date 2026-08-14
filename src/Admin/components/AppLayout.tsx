import { Component, type ReactNode } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { LayoutDashboard, Sparkles, Rows3, Boxes, GitBranch, Database, BarChart3, Zap, Layers, GitCommitHorizontal, Search, Share2, FileSearch, FileCog, Stethoscope, Box, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button, StatusDot } from './ui';
import { useBootstrap } from '@/lib/hooks';
import { pluginVersion } from '@/lib/api';

const nav = [
  { to: '/overview', label: 'Overview', icon: LayoutDashboard },
  { to: '/setup', label: 'Setup', icon: Sparkles },
  { to: '/queries', label: 'Queries', icon: Rows3 },
  { to: '/content', label: 'Content', icon: Boxes },
  { to: '/relationships', label: 'Relationships', icon: GitBranch },
  { to: '/data', label: 'Data', icon: Database },
  { to: '/reports', label: 'Reports', icon: BarChart3 },
  { to: '/automation', label: 'Automation', icon: Zap },
  { to: '/bulk', label: 'Bulk', icon: Layers },
  { to: '/workflow', label: 'Workflow', icon: GitCommitHorizontal },
  { to: '/search', label: 'Search', icon: Search },
  { to: '/graph', label: 'Graph', icon: Share2 },
  { to: '/inspector', label: 'Inspector', icon: FileSearch },
  { to: '/configuration', label: 'Configuration', icon: FileCog },
  { to: '/diagnostics', label: 'Diagnostics', icon: Stethoscope },
];

class RouteErrorBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
  constructor(props: { children: ReactNode }) {
    super(props);
    this.state = { error: null };
  }
  static getDerivedStateFromError(error: Error) {
    return { error };
  }
  componentDidCatch(error: Error) {
    console.error('CGM Core screen failed to render.', error);
  }
  render() {
    if (this.state.error) {
      return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-rust/25 bg-rust-soft/50 px-6 py-16 text-center">
          <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-surface text-rust shadow-sm">
            <AlertTriangle className="h-5 w-5" />
          </div>
          <p className="font-display text-[15px] font-semibold text-ink">This screen failed to render</p>
          <p className="mt-1 max-w-md text-[13px] text-ink-faint">{this.state.error.message || 'An unexpected error occurred.'}</p>
          <Button variant="outline" size="sm" className="mt-4" onClick={() => this.setState({ error: null })}>Try again</Button>
        </div>
      );
    }
    return this.props.children;
  }
}

export function AppLayout() {
  const location = useLocation();
  const bootstrap = useBootstrap();
  const version = pluginVersion();
  const counts = bootstrap.data?.counts;
  const readyProviders = bootstrap.data?.providers.filter((p) => p.status !== 'incompatible' && p.status !== 'legacy-registration').length ?? 0;
  const totalProviders = bootstrap.data?.providers.length ?? 0;
  const connectedBuilders = bootstrap.data?.builders.filter((b) => b.detected).length ?? 0;

  return (
    <div className="cgm-core-root">
      <div className="flex flex-col gap-7">
        {/* Masthead */}
        <header className="rise flex items-center justify-between gap-4">
          <div className="flex items-center gap-3.5">
            <div className="relative flex h-11 w-11 items-center justify-center rounded-xl bg-indigo text-white shadow-card">
              <Box className="h-5.5 w-5.5" />
              <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-[hsl(var(--paper))] bg-amber-bright" />
            </div>
            <div>
              <h1 className="font-display text-[22px] font-bold leading-tight tracking-tight text-ink">CGM Core</h1>
              <p className="font-mono text-[11px] uppercase tracking-widest text-ink-faint">Content OS · control room</p>
            </div>
          </div>
          <div className="flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-1.5 shadow-card">
            <StatusDot tone={readyProviders === totalProviders && totalProviders > 0 ? 'pine' : 'gold'} pulse />
            <span className="text-[13px] font-medium text-ink-soft">
              {totalProviders ? `${readyProviders}/${totalProviders} providers · ${connectedBuilders} builders` : 'Initializing…'}
            </span>
            {version && <span className="font-mono text-[11px] text-ink-faint">v{version}</span>}
          </div>
        </header>

        {/* Primary nav */}
        <nav className="rise flex gap-0.5 overflow-x-auto border-b border-line" style={{ animationDelay: '60ms' }}>
          {nav.map(({ to, label, icon: Icon }) => {
            const active = location.pathname === to;
            return (
              <NavLink
                key={to}
                to={to}
                className={cn(
                  'group relative flex items-center gap-2 whitespace-nowrap px-4 py-3 text-[13.5px] font-medium transition-colors duration-150',
                  active ? 'text-indigo-ink' : 'text-ink-faint hover:text-ink',
                )}
              >
                <Icon className={cn('h-4 w-4 transition-transform duration-200', active ? 'text-indigo-bright' : 'group-hover:scale-110')} />
                {label}
                <span
                  className={cn(
                    'absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-indigo-bright transition-all duration-300',
                    active ? 'opacity-100' : 'opacity-0',
                  )}
                />
              </NavLink>
            );
          })}
        </nav>

        <main className="min-h-[420px]">
          <RouteErrorBoundary key={location.pathname}>
            <Outlet context={{ counts }} />
          </RouteErrorBoundary>
        </main>
      </div>
    </div>
  );
}
