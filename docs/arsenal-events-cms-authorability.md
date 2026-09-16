# Arsenal Events CMS Authorability

This document records the retained local authoring contract for the Arsenal Events CMS model.

## Field Contract

| Surface | Author UI | ACF storage | REST payload | Frontend consumer |
| --- | --- | --- | --- | --- |
| Default page blocks | Page edit screen > Page Blocks | `page_blocks` flexible content, retained layouts `hero_block` and `services_block` | `/meza/v1/pages`, `/meza/v1/pages/{slug}`: `page_blocks`, canonical `blocks` mapping | page renderer and block adapters |
| Global contact | Arsenal Events > Settings > Global Site Settings | `options_site_email`, `options_site_phone` | `/meza/v1/settings`: `contact.email`, `contact.phone`, temporary aliases `site_email`, `site_phone` | `frontend/src/lib/wordpress/client.ts`, layout header/footer binds |
| Global social/nav/copyright | Arsenal Events > Settings > Global Site Settings | `options_social_links`, `options_header_nav_links`, `options_footer_nav_links`, `options_site_info_text`, `options_site_copyright_text` | `/meza/v1/settings`: `social_links`, `header_nav_links`, `footer_nav_links`, `site_info_text`, `site_copyright_text` | layout header/footer binds |
| Race details | Race edit screen > Race Details | `race_date`, `race_location`, `race_registration_link`, `race_results_link`, `race_featured_image` | `/meza/v1/races`, `/meza/v1/races/{slug}` | race list/detail and Races & Results pages |
| Resource details | Resource edit screen > Resource Details | `resource_featured_image`, `resource_download_link`, `resource_external_link` | `/meza/v1/resources`, `/meza/v1/resources/{slug}` | resource list/detail and Race Director pages |
| Race Director hero | Arsenal Events > Media Library > RD Hero - Media Library | `options_rd_hero_*` | `/meza/v1/arsenal-media`: `acf.rd_hero_*`, `media.rd_hero_*` | `getRDHeroMediaData()` |
| Race Director services | Arsenal Events > Media Library > RD Services - Media Library | `options_rd_services_title`, `options_rd_services_description`, `options_rd_services_items` | `/meza/v1/arsenal-media`: `acf.rd_services_*`, `media.rd_services_*` | `getRDServicesMediaData()` |
| Race Director gallery | Arsenal Events > Media Library > RD Gallery - Media Library | `options_rd_gallery_title`, `options_rd_gallery_items` | `/meza/v1/arsenal-media`: `acf.rd_gallery_*`, `media.rd_gallery_*` | `getRDGalleryMediaData()` |
| Races & Results hero | Arsenal Events > Media Library > RR Hero - Media Library | `options_rr_hero_*` | `/meza/v1/arsenal-media`: `acf.rr_hero_*`, `media.rr_hero_*` | `getRRHeroMediaData()` |

Race Director and Races & Results page-template field groups are intentionally not active author UI. REST and the frontend read the options/settings source above.

Page authoring is intentionally limited to `hero_block` and `services_block` because those are the layouts currently rendered by the frontend. The previously registered global header/footer page-block layouts are superseded by Global Settings options fields, and the other non-rendered page layouts remain unavailable to authors until a renderer exists.

## Localities Deduplication

The duplicate `List Localities Section` registration used the same ACF group key, `group_9f5b5c9a`. The retained record is the lowest published field-group post ID. Duplicate records are set to `acf-disabled` rather than deleted so existing field data is preserved and the change remains reversible.

## Media Verification

Media fields use the WordPress media library (`library=all`) and return arrays. REST normalizes referenced image/file values with attachment IDs, URLs, alt text, titles, captions, descriptions, and available image sizes where applicable.

Current documented dimension violations, left unchanged:

| Field | Attachment ID | Actual | Required |
| --- | ---: | --- | --- |
| `rd_hero_image` | 236 | 1600x488 | 1200x600 |
| `rd_services_items[0].rd_service_image` | 238 | 300x91 | 400x300 |
| `rd_services_items[3].rd_service_image` | 241 | 200x257 | 400x300 |
