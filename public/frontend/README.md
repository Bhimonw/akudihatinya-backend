# Frontend Build Artifacts

This directory is the target of the Vite build (see `frontend-akudihatinya/vite.config.js`).

Ignored artifacts:
- All generated files are git-ignored (`public/frontend/*`) except this README (and optional `.gitkeep`).

Deployment Flow:
1. Run `npm run build` inside `frontend-akudihatinya/`.
2. Assets are emitted here and served directly by Laravel (e.g. `/frontend/index.html`).
3. CI should run the build step prior to packaging/deployment.

Do not manually edit generated files here; modify source in `frontend-akudihatinya/src` instead.
