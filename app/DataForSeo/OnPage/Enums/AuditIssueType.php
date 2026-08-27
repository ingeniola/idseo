<?php

declare(strict_types=1);

namespace App\DataForSeo\OnPage\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Catálogo curado de issues de on_page/pages (sección 4 del SPEC: "el
 * catálogo de tipos de issue debe vivir en un enum PHP con etiquetas
 * en español, no en strings sueltos").
 *
 * on_page/pages devuelve un objeto `checks` con 60+ flags booleanos,
 * pero NO todos significan "true = problema": algunos son solo hechos
 * descriptivos (ej. is_https, is_www, is_http son verdadero/falso
 * sobre el protocolo/subdominio de la URL, no indican un error).
 * docs.dataforseo.com está bloqueado en este entorno (igual que en
 * los demás módulos de este proyecto) — cada caso de este enum es uno
 * que se pudo confirmar por búsqueda cruzada como "true = problema
 * real", nunca uno inventado.
 *
 * Deliberadamente NO hay un caso "Other"/catch-all: si
 * ProcessOnPageAuditPostback encuentra un check en `true` que no está
 * en este enum, no genera una fila de issue para él — no sabemos si
 * ese check significa "mal" o es solo un dato neutro, y SPEC prohíbe
 * inventar. Esto es intencionalmente conservador: mejor no reportar
 * un issue real que inventar uno falso.
 */
enum AuditIssueType: string implements HasLabel
{
    // Confirmados directamente contra un ejemplo real de checks{} de
    // on_page/pages (WebSearch, docs.dataforseo.com/v3/on_page-pages/).
    case IsBroken = 'is_broken';
    case Is4xxCode = 'is_4xx_code';
    case Is5xxCode = 'is_5xx_code';
    case IsRedirect = 'is_redirect';
    case HighLoadingTime = 'high_loading_time';
    case HighWaitingTime = 'high_waiting_time';
    case NoContentEncoding = 'no_content_encoding';

    // Confirmados por nombre contra documentación/artículos de
    // DataForSEO sobre las 120 métricas de OnPage API y su propia
    // clasificación de severidad (dataforseo.com/blog/120-onpage-api-metrics-explained).
    case NoTitle = 'no_title';
    case DuplicateTitle = 'duplicate_title';
    case NoDescription = 'no_description';
    case DuplicateDescription = 'duplicate_description';
    case NoH1Tag = 'no_h1_tag';
    case LowContentRate = 'low_content_rate';
    case NoFavicon = 'no_favicon';

    public function getLabel(): string
    {
        return match ($this) {
            self::IsBroken => 'Enlace roto',
            self::Is4xxCode => 'Código de error 4xx',
            self::Is5xxCode => 'Código de error 5xx',
            self::IsRedirect => 'Redirección',
            self::HighLoadingTime => 'Tiempo de carga alto',
            self::HighWaitingTime => 'Tiempo de espera alto',
            self::NoContentEncoding => 'Sin compresión de contenido',
            self::NoTitle => 'Sin etiqueta title',
            self::DuplicateTitle => 'Title duplicado',
            self::NoDescription => 'Sin meta description',
            self::DuplicateDescription => 'Meta description duplicada',
            self::NoH1Tag => 'Sin etiqueta H1',
            self::LowContentRate => 'Poco contenido de texto',
            self::NoFavicon => 'Sin favicon',
        };
    }

    public function severity(): AuditIssueSeverity
    {
        return match ($this) {
            self::IsBroken, self::Is5xxCode, self::NoTitle => AuditIssueSeverity::Critical,
            self::Is4xxCode, self::IsRedirect, self::HighLoadingTime, self::HighWaitingTime,
            self::DuplicateTitle, self::NoDescription, self::DuplicateDescription,
            self::NoH1Tag, self::LowContentRate => AuditIssueSeverity::Warning,
            self::NoContentEncoding, self::NoFavicon => AuditIssueSeverity::Notice,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::IsBroken => 'La página devuelve un error al cargarse.',
            self::Is4xxCode => 'La página devuelve un código de error 4xx (ej. 404).',
            self::Is5xxCode => 'La página devuelve un código de error 5xx del servidor.',
            self::IsRedirect => 'La página redirige a otra URL.',
            self::HighLoadingTime => 'La página tarda demasiado en cargar.',
            self::HighWaitingTime => 'El servidor tarda demasiado en responder (TTFB alto).',
            self::NoContentEncoding => 'La respuesta no usa compresión de contenido (gzip/br).',
            self::NoTitle => 'La página no tiene etiqueta <title>.',
            self::DuplicateTitle => 'El title de esta página se repite en otra página del sitio.',
            self::NoDescription => 'La página no tiene meta description.',
            self::DuplicateDescription => 'La meta description de esta página se repite en otra página del sitio.',
            self::NoH1Tag => 'La página no tiene etiqueta H1.',
            self::LowContentRate => 'La proporción de texto respecto al HTML es baja.',
            self::NoFavicon => 'El sitio no tiene favicon.',
        };
    }
}
