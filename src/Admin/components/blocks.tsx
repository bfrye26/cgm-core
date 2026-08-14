import { cn, formatNumber } from '@/lib/utils';
import { Card, type Tone } from './ui';

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
