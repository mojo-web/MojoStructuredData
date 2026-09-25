# Mojo Structured Data (JSON-LD) – Shopware 6

Rendert Schema.org-JSON-LD in den `<head>` **jeder Storefront-Seite**:

| Seitentyp | Ausgegebene Blöcke |
|-----------|--------------------|
| Jede Seite | `OnlineStore` (Organisation mit Adresse, Kontakt, Öffnungszeiten, Marken, `knowsAbout`, Zahlungsarten, `sameAs`) |
| Produktdetailseite | `Product` + `Offer` (Preis, Währung, Verfügbarkeit, Marke/Hersteller, SKU/MPN/GTIN, Bild) + `BreadcrumbList` |
| Kategorieseite | `CollectionPage` + `BreadcrumbList` |
| Inhalts-/Landingpage | `WebPage` |

Optimiert für KI-/LLM-Sichtbarkeit (ChatGPT, Perplexity, Gemini, Claude) und klassische Rich Results.

## Installation

```bash
# Plugin nach custom/plugins/ kopieren, dann:
bin/console plugin:refresh
bin/console plugin:install --activate MojoStructuredData
bin/console cache:clear
```

Bei Bedarf Storefront neu bauen: `bin/console theme:compile` ist **nicht** nötig
(es wird kein CSS/JS hinzugefügt, nur ein Twig-Block) – ein `cache:clear` genügt.

## Konfiguration

Admin → Einstellungen → Erweiterungen → **Mojo Structured Data (JSON-LD)**.

- Alle Unternehmensfelder sind vorbelegt (deine Werte) und pro Sales Channel überschreibbar.
- `URL` leer lassen ⇒ es wird automatisch die Domain des Sales Channels verwendet.
- Marken / Themen / Zahlungsarten / `sameAs`: ein Eintrag pro Zeile; leer = Plugin-Standardwerte.
- Je Seitentyp einzeln aktivierbar/deaktivierbar.

## Technische Hinweise

- Hook: `StorefrontRenderEvent` – feuert bei jedem vollständigen Seitenrender; AJAX-/Widget-Render
  erhalten den `<head>`-Block nicht, daher keine doppelte Ausgabe.
- Ausgabe erfolgt über die Twig-Erweiterung `storefront/layout/meta.html.twig`
  (Block `layout_head_meta_tags`).
- JSON wird mit `JSON_HEX_TAG` kodiert ⇒ kein `</script>`-Breakout möglich.
- Die Unterscheidung Kategorie vs. Inhaltsseite bei `NavigationPage` nutzt – falls geladen –
  den CMS-Page-Typ (`product_list` ⇒ CollectionPage, sonst WebPage). Ist die CMS-Assoziation
  an der Navigations-Kategorie nicht geladen, wird standardmäßig `CollectionPage` ausgegeben.

## Mögliche Erweiterungen

- `BreadcrumbList`-Einträge zusätzlich mit absoluten URLs versehen (derzeit Name + Position).
- `aggregateRating` / `review` aus einem Bewertungs-Connector (z. B. ProvenExpert) ergänzen.
- `ItemList` der gelisteten Produkte auf Kategorieseiten ausgeben.
