<?php declare(strict_types=1);

namespace Mojo\StructuredData\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds Schema.org JSON-LD structures (as plain arrays) for the storefront.
 *
 * All organization values fall back to baked-in defaults so the plugin emits
 * correct data out of the box; every value can be overridden in the admin.
 */
class StructuredDataBuilder
{
    private const PREFIX = 'MojoStructuredData.config.';

    private const DEFAULT_DESCRIPTION = 'A&B Bürokommunikation ist Ihr Spezialist für Postbearbeitungsmaschinen '
        . 'mit Sitz bei Hannover. Wir bieten Frankiermaschinen, Kuvertiermaschinen, Falzmaschinen, elektrische '
        . 'Brieföffner, Aktenvernichter sowie Verbrauchsmaterial und Sicherheitssoftware. Als autorisierter '
        . 'Vertriebs- und Servicepartner von Francotyp-Postalia und Frama betreuen wir Kunden bundesweit.';

    private const DEFAULT_BRANDS = [
        'Francotyp-Postalia', 'Frama', 'Quadient', 'Pitney Bowes', 'REINER', 'ESET', 'OKI',
    ];

    private const DEFAULT_KNOWS = [
        'Frankiermaschinen', 'Frankiersysteme', 'Freistempler', 'Kuvertiermaschinen', 'Falzmaschinen',
        'Tisch-Kuvertiermaschinen', 'Elektrische Brieföffner', 'Aktenvernichter', 'Briefschließer',
        'Briefzählmaschinen', 'Kennzeichnungsgeräte', 'Postbearbeitungsmaschinen',
        'Frankiermaschinen Verbrauchsmaterial', 'Tinte und Toner', 'Sicherheitssoftware', 'Backup-Software',
        'Leasing von Bürotechnik', 'Service und Reparatur Frankiermaschinen', 'EDV-Betreuung Region Hannover',
    ];

    private const DEFAULT_PAYMENT = [
        'PayPal', 'Mastercard', 'VISA', 'SEPA-Lastschrift', 'Vorkasse', 'Rechnung',
    ];

    private const DEFAULT_SAMEAS = [
        'https://www.facebook.com/frankiersysteme/',
        'https://www.youtube.com/@FrankiersystemeDe',
        'https://www.instagram.com/frankiersysteme/',
        'https://www.xing.com/profile/Jens_Behre3',
        'https://frankiersysteme.blogspot.com/',
        'https://www.provenexpert.com/ab-buerokommunikation',
    ];

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function isEnabled(string $key, ?string $channelId): bool
    {
        $value = $this->systemConfig->get(self::PREFIX . $key, $channelId);

        return $value === null ? true : (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOrganization(SalesChannelContext $context, Request $request): array
    {
        $channelId = $context->getSalesChannelId();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => $this->cfg('name', $channelId, 'A&B Bürokommunikation'),
            'alternateName' => $this->cfg('alternateName', $channelId, 'Frankiersysteme.de'),
            'url' => $this->cfg('url', $channelId, null) ?? $request->getSchemeAndHttpHost() . '/',
            'description' => $this->cfg('description', $channelId, self::DEFAULT_DESCRIPTION),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $this->cfg('streetAddress', $channelId, 'Alt-Godshorn 79'),
                'postalCode' => $this->cfg('postalCode', $channelId, '30855'),
                'addressLocality' => $this->cfg('addressLocality', $channelId, 'Langenhagen'),
                'addressRegion' => $this->cfg('addressRegion', $channelId, 'Niedersachsen'),
                'addressCountry' => $this->cfg('addressCountry', $channelId, 'DE'),
            ],
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'telephone' => $this->cfg('telephone', $channelId, '+49-511-97329790'),
                    'contactType' => 'customer service',
                    'email' => $this->cfg('email', $channelId, 'info@frankiersysteme.de'),
                    'areaServed' => 'DE',
                    'availableLanguage' => 'German',
                    'hoursAvailable' => [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => $this->days($channelId),
                        'opens' => $this->cfg('opens', $channelId, '08:00'),
                        'closes' => $this->cfg('closes', $channelId, '16:30'),
                    ],
                ],
            ],
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Deutschland',
            ],
            'brand' => array_map(
                static fn (string $name): array => ['@type' => 'Brand', 'name' => $name],
                $this->lines($channelId, 'brands', self::DEFAULT_BRANDS)
            ),
            'knowsAbout' => $this->lines($channelId, 'knowsAbout', self::DEFAULT_KNOWS),
            'paymentAccepted' => $this->lines($channelId, 'paymentAccepted', self::DEFAULT_PAYMENT),
            'sameAs' => $this->lines($channelId, 'sameAs', self::DEFAULT_SAMEAS),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProduct(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
        Request $request
    ): array {
        $url = $this->currentUrl($request);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getTranslation('name') ?? $product->getName() ?? '',
            'sku' => $product->getProductNumber(),
            'url' => $url,
        ];

        $description = $this->plain($product->getTranslation('description') ?? $product->getDescription());
        if ($description !== '') {
            $data['description'] = $description;
        }

        $manufacturer = $product->getManufacturer();
        if ($manufacturer !== null) {
            $data['brand'] = [
                '@type' => 'Brand',
                'name' => $manufacturer->getTranslation('name') ?? $manufacturer->getName() ?? '',
            ];
        }

        if ($product->getManufacturerNumber()) {
            $data['mpn'] = $product->getManufacturerNumber();
        }
        if ($product->getEan()) {
            $data['gtin'] = $product->getEan();
        }

        $media = $product->getCover()?->getMedia();
        if ($media !== null && $media->getUrl()) {
            $data['image'] = $media->getUrl();
        }

        $price = $product->getCalculatedPrice()?->getUnitPrice();
        if ($price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => $context->getCurrency()->getIsoCode(),
                'price' => number_format($price, 2, '.', ''),
                'availability' => $this->availability($product),
                'itemCondition' => 'https://schema.org/NewCondition',
                'url' => $url,
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $this->cfg('name', $context->getSalesChannelId(), 'A&B Bürokommunikation'),
                ],
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCollectionPage(CategoryEntity $category, Request $request): array
    {
        return $this->buildPage('CollectionPage', $category, $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildWebPage(CategoryEntity $category, Request $request): array
    {
        return $this->buildPage('WebPage', $category, $request);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildBreadcrumb(?CategoryEntity $category, Request $request): ?array
    {
        if ($category === null) {
            return null;
        }

        $names = $category->getBreadcrumb();
        if (!\is_array($names) || $names === []) {
            return null;
        }

        $items = [];
        $position = 1;
        foreach (array_values($names) as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Encodes a block into a JSON string that is safe to print inside a <script> tag.
     * JSON_HEX_TAG turns "<"/">" into \u003C/\u003E and prevents a "</script>" breakout.
     *
     * @param array<string, mixed> $data
     */
    public function encode(array $data): string
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return $json !== false ? $json : '{}';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPage(string $type, CategoryEntity $category, Request $request): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $category->getTranslation('metaTitle')
                ?? $category->getMetaTitle()
                ?? $category->getTranslation('name')
                ?? $category->getName()
                ?? '',
            'url' => $this->currentUrl($request),
        ];

        $description = $this->plain(
            $category->getTranslation('metaDescription')
            ?? $category->getMetaDescription()
            ?? $category->getTranslation('description')
            ?? $category->getDescription()
        );
        if ($description !== '') {
            $data['description'] = $description;
        }

        return $data;
    }

    private function availability(SalesChannelProductEntity $product): string
    {
        $stock = $product->getAvailableStock() ?? 0;
        if ($stock > 0) {
            return 'https://schema.org/InStock';
        }

        return $product->getIsCloseout()
            ? 'https://schema.org/OutOfStock'
            : 'https://schema.org/BackOrder';
    }

    private function currentUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . $request->getPathInfo();
    }

    private function cfg(string $key, ?string $channelId, ?string $default): ?string
    {
        $value = $this->systemConfig->getString(self::PREFIX . $key, $channelId);

        return $value !== '' ? $value : $default;
    }

    /**
     * @return list<string>
     */
    private function days(?string $channelId): array
    {
        $value = $this->systemConfig->get(self::PREFIX . 'openDays', $channelId);
        if (\is_array($value) && $value !== []) {
            return array_values(array_filter(array_map('strval', $value), static fn (string $v): bool => $v !== ''));
        }

        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    }

    /**
     * @param list<string> $default
     *
     * @return list<string>
     */
    private function lines(?string $channelId, string $key, array $default): array
    {
        $raw = $this->systemConfig->getString(self::PREFIX . $key, $channelId);
        if (trim($raw) === '') {
            return $default;
        }

        $parts = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $parts = array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $value): bool => $value !== ''
        ));

        return $parts === [] ? $default : $parts;
    }

    private function plain(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return mb_substr($text, 0, 5000);
    }
}
