Preview assets originate from https://github.com/dovahkiinn-5/SD-Ceria-Nusantara---Landing-Page.git at cb613dc, with the shared teacher-content and configurable campus-photo fixes described in docs/WEBSITE-INTEGRATION.md.

The accompanying Blade templates are in resources/views/website/site and resources/views/components/landing-preview. They render authenticated draft previews using dashboard data, without a runtime dependency on another checkout or server.

Refresh from a reviewed landing checkout with `python tools/sync-landing-preview.py PATH_TO_LANDING`, then run the PHP feature tests and `npm run test:editor`.
