import { useEffect, type ReactNode } from 'react';
import { X, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from './ui';

interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  desc?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
  wide?: boolean;
}

export function Dialog({ open, onClose, title, desc, children, footer, wide }: DialogProps) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-ink/40 backdrop-blur-[2px] animate-[cgm-fade_0.15s_ease-out]" onClick={onClose} />
      <div className={cn('relative w-full rounded-xl border border-line bg-surface shadow-lift animate-[cgm-dialog_0.2s_cubic-bezier(0.22,1,0.36,1)]', wide ? 'max-w-2xl' : 'max-w-md')}>
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div>
            <h3 className="font-display text-[16px] font-semibold text-ink">{title}</h3>
            {desc && <p className="mt-0.5 text-[13px] text-ink-faint">{desc}</p>}
          </div>
          <button onClick={onClose} className="rounded-md p-1 text-ink-faint transition hover:bg-surface-2 hover:text-ink">
            <X className="h-4 w-4" />
          </button>
        </div>
        {children && <div className="max-h-[60vh] overflow-y-auto px-5 py-4">{children}</div>}
        {footer && <div className="flex justify-end gap-2 border-t border-line bg-surface-2/40 px-5 py-3.5">{footer}</div>}
      </div>
    </div>
  );
}

interface ConfirmProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  desc?: ReactNode;
  confirmLabel?: string;
  danger?: boolean;
  loading?: boolean;
}

export function ConfirmDialog({ open, onClose, onConfirm, title, desc, confirmLabel = 'Confirm', danger, loading }: ConfirmProps) {
  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={
        <span className="flex items-center gap-2">
          {danger && <AlertTriangle className="h-4 w-4 text-rust" />}
          {title}
        </span>
      }
      desc={desc}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading}>
            {confirmLabel}
          </Button>
        </>
      }
    />
  );
}
