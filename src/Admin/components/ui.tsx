import { useEffect, useRef, useState, type ReactNode, type ButtonHTMLAttributes, type InputHTMLAttributes, type TextareaHTMLAttributes, type SelectHTMLAttributes } from 'react';
import { cn, formatNumber } from '@/lib/utils';

export type Tone = 'indigo' | 'amber' | 'neutral' | 'pine' | 'gold' | 'rust';

/* ── Button ─────────────────────────────────────────────────────────── */
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'outline' | 'amber';
interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: 'sm' | 'md';
  loading?: boolean;
  icon?: ReactNode;
}
const variants: Record<ButtonVariant, string> = {
  primary: 'bg-indigo text-white hover:bg-indigo-bright shadow-sm',
  amber: 'bg-amber text-white hover:bg-amber-bright shadow-sm',
  secondary: 'bg-surface-2 text-ink hover:bg-line',
  ghost: 'bg-transparent text-ink-soft hover:bg-surface-2 hover:text-ink',
  danger: 'bg-rust text-white hover:brightness-110 shadow-sm',
  outline: 'bg-surface text-ink border border-line-strong hover:border-indigo hover:text-indigo-ink',
};
export function Button({ variant = 'primary', size = 'md', loading, icon, className, children, disabled, type = 'button', ...rest }: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-all duration-150',
        'focus-visible:outline-none focus-visible:shadow-focus active:translate-y-px',
        'disabled:pointer-events-none disabled:opacity-50',
        size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-9 px-4 text-sm',
        variants[variant],
        className,
      )}
      disabled={disabled || loading}
      {...rest}
    >
      {loading ? <Spinner className="h-3.5 w-3.5" /> : icon}
      {children}
    </button>
  );
}

/* ── Spinner ────────────────────────────────────────────────────────── */
export function Spinner({ className }: { className?: string }) {
  return (
    <svg className={cn('animate-[cgm-spin_0.7s_linear_infinite]', className ?? 'h-4 w-4')} viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeOpacity="0.25" strokeWidth="3" />
      <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
  );
}

/* ── Card ───────────────────────────────────────────────────────────── */
interface CardProps {
  className?: string;
  children: ReactNode;
  hover?: boolean;
  style?: React.CSSProperties;
}
export function Card({ className, children, hover, style }: CardProps) {
  return (
    <div
      style={style}
      className={cn(
        'rounded-lg border border-line bg-surface shadow-card',
        hover && 'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lift',
        className,
      )}
    >
      {children}
    </div>
  );
}
export function CardHeader({ title, desc, action, className }: { title: ReactNode; desc?: ReactNode; action?: ReactNode; className?: string }) {
  return (
    <div className={cn('flex items-start justify-between gap-3 border-b border-line px-5 py-4', className)}>
      <div>
        <h3 className="font-display text-[15px] font-semibold text-ink">{title}</h3>
        {desc && <p className="mt-0.5 text-[13px] text-ink-faint">{desc}</p>}
      </div>
      {action}
    </div>
  );
}
export function CardBody({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn('px-5 py-4', className)}>{children}</div>;
}

/* ── Node chip (connection-dot motif) ───────────────────────────────── */
const toneClass: Record<Tone, string> = {
  indigo: 'node-chip',
  amber: 'node-chip node-chip--amber',
  neutral: 'node-chip node-chip--neutral',
  pine: 'node-chip node-chip--pine',
  gold: 'node-chip node-chip--gold',
  rust: 'node-chip node-chip--rust',
};
export function NodeChip({ tone = 'indigo', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  return <span className={cn(toneClass[tone], className)}>{children}</span>;
}

export function StatusDot({ tone = 'indigo', pulse }: { tone?: Tone; pulse?: boolean }) {
  const colors: Record<Tone, string> = {
    indigo: 'bg-indigo-bright',
    amber: 'bg-amber-bright',
    neutral: 'bg-ink-faint',
    pine: 'bg-pine',
    gold: 'bg-gold',
    rust: 'bg-rust',
  };
  return <span className={cn('inline-block h-2 w-2 shrink-0 rounded-full', colors[tone], pulse && 'animate-[cgm-pulse_1.8s_ease-in-out_infinite]')} />;
}

/* ── Stat with count-up ─────────────────────────────────────────────── */
function useCountUp(target: number, duration = 700) {
  const [value, setValue] = useState(0);
  const raf = useRef(0);
  useEffect(() => {
    const start = performance.now();
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      setValue(Math.round(target * eased));
      if (t < 1) raf.current = requestAnimationFrame(tick);
    };
    raf.current = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf.current);
  }, [target, duration]);
  return value;
}
export function Stat({ label, value, icon, tone = 'indigo', delay = 0, hint, suffix }: { label: string; value?: number | string; icon?: ReactNode; tone?: Tone; delay?: number; hint?: string; suffix?: string }) {
  const numeric = typeof value === 'number' ? value : undefined;
  const animated = useCountUp(numeric ?? 0);
  const accents: Record<Tone, string> = {
    indigo: 'text-indigo-bright bg-indigo-soft',
    amber: 'text-amber-bright bg-amber-soft',
    neutral: 'text-ink-soft bg-surface-2',
    pine: 'text-pine bg-pine-soft',
    gold: 'text-gold bg-gold-soft',
    rust: 'text-rust bg-rust-soft',
  };
  const display = value === undefined ? null : typeof value === 'string' ? value : <>{formatNumber(animated)}{suffix}</>;
  return (
    <Card hover className="rise p-5" style={{ animationDelay: `${delay}ms` }}>
      <div className="flex items-center justify-between">
        <span className="eyebrow">{label}</span>
        {icon && <span className={cn('flex h-8 w-8 items-center justify-center rounded-md', accents[tone])}>{icon}</span>}
      </div>
      <div className="mt-3 font-display text-[40px] font-bold leading-none tracking-tight text-ink tabular-nums">
        {display ?? <span className="skeleton inline-block h-9 w-16 align-middle" />}
      </div>
      {hint && <p className="mt-2 text-[12px] text-ink-faint">{hint}</p>}
    </Card>
  );
}

/* ── Sub tabs ───────────────────────────────────────────────────────── */
export function SubTabs<T extends string>({ tabs, active, onChange }: { tabs: { id: T; label: string; count?: number }[]; active: T; onChange: (id: T) => void }) {
  return (
    <div className="mb-5 inline-flex gap-1 rounded-lg border border-line bg-surface-2 p-1">
      {tabs.map((tab) => (
        <button
          key={tab.id}
          onClick={() => onChange(tab.id)}
          className={cn(
            'relative rounded-md px-3.5 py-1.5 text-[13px] font-medium transition-all duration-150',
            active === tab.id ? 'bg-surface text-ink shadow-sm' : 'text-ink-faint hover:text-ink',
          )}
        >
          {tab.label}
          {tab.count !== undefined && tab.count > 0 && (
            <span className={cn('ml-1.5 rounded-full px-1.5 py-px font-mono text-[10px]', active === tab.id ? 'bg-indigo-soft text-indigo-ink' : 'bg-line text-ink-faint')}>
              {tab.count}
            </span>
          )}
        </button>
      ))}
    </div>
  );
}

/* ── Form controls ──────────────────────────────────────────────────── */
export function Label({ children, htmlFor }: { children: ReactNode; htmlFor?: string }) {
  return (
    <label htmlFor={htmlFor} className="mb-1.5 block text-[13px] font-medium text-ink-soft">
      {children}
    </label>
  );
}
const inputBase =
  'w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-ink-faint/70 transition-all duration-150 focus:border-indigo-bright focus:outline-none focus:shadow-focus disabled:opacity-50';
export function Input({ className, ...rest }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(inputBase, 'h-9', className)} {...rest} />;
}
export function Textarea({ className, ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn(inputBase, 'min-h-[80px] py-2', className)} {...rest} />;
}
export function Select({ className, children, ...rest }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={cn(inputBase, 'h-9 cursor-pointer appearance-none pr-8', className)} {...rest}>
      {children}
    </select>
  );
}
export function Switch({ checked, onChange, disabled }: { checked: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className={cn(
        'relative h-[22px] w-10 shrink-0 rounded-full transition-colors duration-200 focus-visible:outline-none focus-visible:shadow-focus disabled:opacity-40',
        checked ? 'bg-indigo' : 'bg-line-strong',
      )}
    >
      <span className={cn('absolute top-[3px] h-4 w-4 rounded-full bg-white shadow transition-all duration-200', checked ? 'left-[21px]' : 'left-[3px]')} />
    </button>
  );
}
export function ToggleRow({ label, desc, checked, onChange, disabled }: { label: string; desc?: string; checked: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-line py-3 last:border-0">
      <div>
        <p className="text-sm font-medium text-ink">{label}</p>
        {desc && <p className="mt-0.5 text-[12.5px] text-ink-faint">{desc}</p>}
      </div>
      <Switch checked={checked} onChange={onChange} disabled={disabled} />
    </div>
  );
}
export function Checkbox({ label, checked, onChange }: { label: ReactNode; checked: boolean; onChange: (v: boolean) => void }) {
  return (
    <label className="flex cursor-pointer items-center gap-2.5 text-[13.5px] text-ink-soft">
      <button
        type="button"
        role="checkbox"
        aria-checked={checked}
        onClick={() => onChange(!checked)}
        className={cn(
          'flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded border transition-colors duration-150',
          checked ? 'border-indigo bg-indigo text-white' : 'border-line-strong bg-surface',
        )}
      >
        {checked && (
          <svg className="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        )}
      </button>
      {label}
    </label>
  );
}

/* ── Empty state ────────────────────────────────────────────────────── */
export function EmptyState({ icon, title, desc, action }: { icon?: ReactNode; title: string; desc?: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-line-strong bg-surface-2/50 px-6 py-12 text-center">
      {icon && <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-surface text-ink-faint shadow-sm">{icon}</div>}
      <p className="font-display text-[15px] font-semibold text-ink">{title}</p>
      {desc && <p className="mt-1 max-w-sm text-[13px] text-ink-faint">{desc}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

/* ── Skeleton rows ──────────────────────────────────────────────────── */
export function SkeletonRows({ rows = 4 }: { rows?: number }) {
  return (
    <div className="space-y-2.5 p-4">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="skeleton h-8" style={{ width: `${88 - i * 9}%` }} />
      ))}
    </div>
  );
}

/* ── Page loading ───────────────────────────────────────────────────── */
export function PageLoader() {
  return (
    <div className="flex h-64 items-center justify-center">
      <div className="flex items-center gap-3 text-ink-faint">
        <Spinner className="h-5 w-5 text-indigo-bright" />
        <span className="text-sm">Loading…</span>
      </div>
    </div>
  );
}

/* ── Notice / banner ────────────────────────────────────────────────── */
export function Notice({ tone = 'indigo', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  const styles: Record<Tone, string> = {
    indigo: 'border-indigo/25 bg-indigo-soft/60 text-indigo-ink',
    amber: 'border-amber/25 bg-amber-soft/60 text-amber-ink',
    neutral: 'border-line bg-surface-2 text-ink-soft',
    pine: 'border-pine/25 bg-pine-soft/60 text-[hsl(163_50%_24%)]',
    gold: 'border-gold/30 bg-gold-soft/60 text-[hsl(32_70%_28%)]',
    rust: 'border-rust/25 bg-rust-soft/60 text-[hsl(8_55%_32%)]',
  };
  return <div className={cn('rounded-md border px-4 py-3 text-[13px]', styles[tone], className)}>{children}</div>;
}
