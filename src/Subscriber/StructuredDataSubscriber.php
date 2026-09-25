<?php declare(strict_types=1);

namespace Mojo\StructuredData\Subscriber;

use Mojo\StructuredData\Service\StructuredDataBuilder;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Product\ProductPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Collects all applicable JSON-LD blocks for the current page and hands them
 * to the template as the "mojoStructuredData" parameter (list of JSON strings).
 *
 * StorefrontRenderEvent fires for every full storefront render, which is the
 * single reliable hook for "render on every page". Page-type specific blocks
 * are derived from the page object available in the render parameters.
 */
class StructuredDataSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly StructuredDataBuilder $builder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onRender',
        ];
    }

    public function onRender(StorefrontRenderEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $request = $event->getRequest();
        $channelId = $context->getSalesChannelId();

        /** @var array<string> $blocks */
        $blocks = [];

        // 1) OnlineStore / Organization on every page.
        if ($this->builder->isEnabled('enableOrganization', $channelId)) {
            $blocks[] = $this->builder->encode($this->builder->buildOrganization($context, $request));
        }

        // 2) Page-type specific structured data.
        $page = $event->getParameters()['page'] ?? null;

        if ($page instanceof ProductPage) {
            if ($this->builder->isEnabled('enableProduct', $channelId)) {
                $product = $page->getProduct();
                $blocks[] = $this->builder->encode($this->builder->buildProduct($product, $context, $request));

                $breadcrumb = $this->builder->buildBreadcrumb($product->getSeoCategory(), $request);
                if ($breadcrumb !== null) {
                    $blocks[] = $this->builder->encode($breadcrumb);
                }
            }
        } elseif ($page instanceof LandingPage) {
            // True CMS landing pages are content pages.
            if ($this->builder->isEnabled('enableContent', $channelId)) {
                // LandingPage has no category; emit a minimal WebPage from the page meta.
                $landing = $page->getLandingPage();
                $blocks[] = $this->builder->encode([
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $landing?->getTranslation('metaTitle')
                        ?? $landing?->getMetaTitle()
                        ?? $landing?->getTranslation('name')
                        ?? $landing?->getName()
                        ?? '',
                    'url' => $request->getSchemeAndHttpHost() . $request->getPathInfo(),
                ]);
            }
        } elseif ($page instanceof NavigationPage) {
            // NavigationPage carries the resolved category and CMS page itself.
            // Do NOT use $page->getHeader() here: Page::getHeader() was deprecated
            // in 6.6 and removed in Shopware 6.7 (header is rendered via ESI).
            $category = $page->getCategory();
            if ($category instanceof CategoryEntity) {
                $cmsType = $page->getCmsPage()?->getType() ?? $category->getCmsPage()?->getType();
                $this->addCategoryBlocks($category, $cmsType, $channelId, $request, $blocks);
            }
        }

        if ($blocks === []) {
            return;
        }

        $event->setParameter('mojoStructuredData', $blocks);
    }

    /**
     * @param array<string> $blocks
     */
    private function addCategoryBlocks(
        CategoryEntity $category,
        ?string $cmsType,
        ?string $channelId,
        \Symfony\Component\HttpFoundation\Request $request,
        array &$blocks
    ): void {
        // Refine listing vs. content page when the CMS page type is available.
        // (If the cmsPage association is missing we default to treating the page
        // as a product listing / category page.)
        $isContent = $cmsType !== null && $cmsType !== 'product_list';

        if ($isContent) {
            if ($this->builder->isEnabled('enableContent', $channelId)) {
                $blocks[] = $this->builder->encode($this->builder->buildWebPage($category, $request));
                $this->appendBreadcrumb($category, $request, $blocks);
            }

            return;
        }

        if ($this->builder->isEnabled('enableCategory', $channelId)) {
            $blocks[] = $this->builder->encode($this->builder->buildCollectionPage($category, $request));
            $this->appendBreadcrumb($category, $request, $blocks);
        }
    }

    /**
     * @param array<string> $blocks
     */
    private function appendBreadcrumb(
        CategoryEntity $category,
        \Symfony\Component\HttpFoundation\Request $request,
        array &$blocks
    ): void {
        $breadcrumb = $this->builder->buildBreadcrumb($category, $request);
        if ($breadcrumb !== null) {
            $blocks[] = $this->builder->encode($breadcrumb);
        }
    }
}
