<?php
namespace CGM\Core\Support;

/** Lightweight locale contract: a filterable locale per object, for future i18n plugins. */
final class Locale {
    public function for_object( mixed $object = null ): string {
        return (string) apply_filters( 'cgm_core/locale', get_locale(), $object );
    }
}
