import { type ReactNode } from 'react';
import { cn, formatNumber } from '@/lib/utils';
import { Card, StatusDot, Button, type Tone } from './ui';

/* ── MetricGrid — compact stat card row ─────────────────────────────── */
export interface Metric {
  label: string;
  value: number | string;
  tone?: Tone;
  hint?: string;
}
export function MetricGrid({ metrics, columns = 4 }: { metrics: Metric[]; columns?: 2 | 3 | 4 }) {
  const grid = columns === 2 ? 'sm:grid-cols-2' : columns === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-4';
  const accents: Record<Tone, string> = {
    indigo: 'text-indigo-bright',
    amber: 'text-amber-bright',
    neutral: 'text-ink',
    pine: 'text-pine',
    gold: 'text-gold',
    rust: 'text-rust',
  };
  return (
    <div className={cn('grid grid-cols-1 gap-3', grid)}>
      {metrics.map((m, i) => (
        <Card key={m.label} className="rise p-4" style={{ animationDelay: `${i * 50}ms` }}>
          <p className="eyebrow">{m.label}</p>
          <p className={cn('mt-1.5 font-display text-[28px] font-bold leading-none tabular-nums', accents[m.tone ?? 'neutral'])}>
            {typeof m.value === 'number' ? formatNumber(m.value) : m.value}
          </p>
          {m.hint && <p className="mt-1.5 text-[11.5px] text-ink-faint">{m.hint}</p>}
        </Card>
      ))}
    </div>
  );
}

/* ── CheckList — status rows with tone + optional action ────────────── */
export interface CheckItem {
  label: string;
  description?: string;
  tone?: Tone;
  value?: ReactNode;
  action?: ReactNode;
}
export function CheckList({ items }: { items: CheckItem[] }) {
  if (!items.length) return null;
  return (
    <div className="divide-y divide-line rounded-lg border border-line bg-surface shadow-card">
      {items.map((item, i) => (
        <div key={i} className="flex items-center gap-3 px-4 py-3">
          <StatusDot tone={item.tone ?? 'neutral'} />
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13.5px] font-medium text-ink">{item.label}</p>
            {item.description && <p className="truncate text-[12.5px] text-ink-faint">{item.description}</p>}
          </div>
          {item.value !== undefined && <span className="shrink-0 font-mono text-[12px] text-ink-soft">{item.value}</span>}
          {item.action}
        </div>
      ))}
    </div>
  );
}

/* ── FeatureCards — action cards with risk-tinted intent ────────────── */
export interface FeatureCard {
  title: string;
  description?: string;
  risk?: 'safe' | 'caution' | 'destructive';
  status?: string;
  statusTone?: Tone;
  actionLabel?: string;
  onAction?: () => void;
  loading?: boolean;
  disabled?: boolean;
}
export function FeatureCards({ cards, columns = 2 }: { cards: FeatureCard[]; columns?: 2 | 3 }) {
  const grid = columns === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2';
  const riskBorder: Record<string, string> = {
    safe: 'border-line hover:border-indigo',
    caution: 'border-gold/40 hover:border-gold',
    destructive: 'border-rust/40 hover:border-rust',
  };
  const riskVariant: Record<string, 'primary' | 'outline' | 'danger'> = {
    safe: 'primary',
    caution: 'outline',
    destructive: 'danger',
  };
  return (
    <div className={cn('grid grid-cols-1 gap-3', grid)}>
      {cards.map((card, i) => (
        <Card key={card.title} hover className={cn('rise flex flex-col p-4', riskBorder[card.risk ?? 'safe'])} style={{ animationDelay: `${i * 50}ms` }}>
          <div className="flex items-start justify-between gap-2">
            <p className="font-display text-[14.5px] font-semibold text-ink">{card.title}</p>
            {card.status && (
              <span className="flex items-center gap-1.5 text-[11.5px] text-ink-faint">
                <StatusDot tone={card.statusTone ?? 'neutral'} />
                {card.status}
              </span>
            )}
          </div>
          {card.description && <p className="mt-1 flex-1 text-[12.5px] text-ink-faint">{card.description}</p>}
          {card.actionLabel && card.onAction && (
            <div className="mt-3">
              <Button size="sm" variant={riskVariant[card.risk ?? 'safe']} onClick={card.onAction} loading={card.loading} disabled={card.disabled}>
                {card.actionLabel}
              </Button>
            </div>
          )}
        </Card>
      ))}
    </div>
  );
}
