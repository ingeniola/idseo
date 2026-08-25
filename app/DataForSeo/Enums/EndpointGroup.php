<?php

declare(strict_types=1);

namespace App\DataForSeo\Enums;

/**
 * Categorías de nivel superior de la API v3 de DataForSEO
 * (https://docs.dataforseo.com/v3/), usadas para agrupar gasto en
 * `cost_ledger` sin recurrir a strings sueltos.
 */
enum EndpointGroup: string
{
    case Serp = 'serp';
    case KeywordsData = 'keywords_data';
    case DataForSeoLabs = 'dataforseo_labs';
    case OnPage = 'on_page';
    case Backlinks = 'backlinks';
    case DomainAnalytics = 'domain_analytics';
    case ContentAnalysis = 'content_analysis';
    case AppData = 'app_data';
    case Merchant = 'merchant';
    case BusinessData = 'business_data';
    case AiOptimization = 'ai_optimization';
    case Appendix = 'appendix';

    public function label(): string
    {
        return match ($this) {
            self::Serp => 'Resultados de búsqueda (SERP)',
            self::KeywordsData => 'Datos de palabras clave',
            self::DataForSeoLabs => 'DataForSEO Labs',
            self::OnPage => 'Auditoría on-page',
            self::Backlinks => 'Backlinks',
            self::DomainAnalytics => 'Analítica de dominio',
            self::ContentAnalysis => 'Análisis de contenido',
            self::AppData => 'Datos de apps',
            self::Merchant => 'Merchant',
            self::BusinessData => 'Datos de negocio',
            self::AiOptimization => 'Optimización para IA (GEO)',
            self::Appendix => 'Utilidades de cuenta',
        };
    }

    /**
     * Deriva el grupo a partir del path del endpoint, ej.
     * "serp/google/organic/task_post" -> EndpointGroup::Serp.
     */
    public static function fromEndpoint(string $endpoint): self
    {
        $prefix = explode('/', ltrim($endpoint, '/'))[0];

        return self::tryFrom($prefix) ?? self::Appendix;
    }
}
