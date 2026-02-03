# Frontend structure (Vue 3 + Inertia)

Base path: `resources/js/`

Folders:

- `assets/` — static assets for front-end (images, fonts) and global styles.
- `components/` — reusable UI components (stateless where possible).
- `composables/` — Vue composables (reusable logic, `useX` functions).
- `layouts/` — page layout shells used by Inertia pages.
- `pages/` — Inertia page components (route targets). Keep shallow nesting by domain.
- `plugins/` — Vue plugin registrations and third-party initializations.
- `services/` — API clients and domain-specific service modules.
- `stores/` — state management (Pinia/Vuex) if/when used.
- `utils/` — helpers and pure utility functions.
- `app.js` — app bootstrap. Inertia resolves pages from `./pages/**/*`.
- `bootstrap.js` — framework/bootstrap initialization.

Conventions:

- Use lowercase folder names as above. Use PascalCase for Vue SFC file names.
- Prefer alias-based imports with `@/` which maps to `resources/js/`.
- Pages must live under `pages/` and be referenced by Inertia via `name` segments.
- Layouts live under `layouts/` and are imported in pages as needed.
- Reusable UI goes to `components/`; avoid cross-imports from `pages/` into `components/`.
- Keep domain subfolders under `pages/` (e.g., `pages/Tasks/`, `pages/Profile/`).
- Composables start with `use` (e.g., `useTasks.ts` / `useTasks.js`).

Build/Resolve:

- Inertia resolver in `app.js` points to `./pages/**/*.vue`.
- `@/` alias points to `resources/js/` (see `jsconfig.json`).
- Keep imports relative within the same folder tree when clearer; otherwise use `@/`.

Example import rules:

```js
// Good
import DashByteLayout from '@/layouts/DashByteLayout.vue'
import InputError from '@/components/InputError.vue'
import useTasks from '@/composables/useTasks'

// Good (relative inside pages domain)
import Toolbar from './_components/Toolbar.vue'

// Avoid (wrong folder case)
// import DashByteLayout from '@/Layouts/DashByteLayout.vue'
```

Backend roles/permissions:

- Roles managed via `spatie/laravel-permission`.
- Standard roles: `Administrator`, `Manager`, `Performer`.
- Seeder creates roles; optional admin user from `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`).

# Структура фронтенд-проекта (Laravel + Vue.js)

## Общие правила разработки
1. **Комментарии в коде пишутся исключительно на русском языке.**  
   Это касается всех файлов проекта: Vue-компоненты, JavaScript/TypeScript, PHP, Blade-шаблоны и любые конфигурационные файлы.  
   Комментарии должны быть понятными и пояснять назначение логики или особенности реализации.  

2. Весь код должен быть структурирован по назначению:  
   - **Общие компоненты и утилиты** — выносим в отдельные директории.  
   - **Страницы по ролям** — разделяем по отдельным папкам для удобства поддержки.  
   - **Layout’ы** — определяются для каждой роли отдельно, чтобы обеспечить различное меню и оформление.  

---

## Структура директорий `resources/js/`

```
resources/js/
├── Components/         # Общие Vue-компоненты (таблицы, формы, модалки и т.п.)
├── Layouts/            # Общие layouts (AdminLayout.vue, ManagerLayout.vue, AppLayout.vue)
├── Pages/
│   ├── Auth/           # Страницы аутентификации (Login, Register, ForgotPassword)
│   ├── Admin/          # Страницы администратора
│   │   ├── Managers/
│   │   │   ├── Index.vue
│   │   ├── Executors/
│   │   │   ├── Index.vue
│   │   ├── Brands/
│   │   │   ├── Index.vue
│   │   │   ├── BrandTasks.vue
│   │   ├── Settings/
│   │   │   ├── TaskTypes.vue
│   │   └── AllTasks.vue
│   ├── Manager/        # Страницы менеджера
│   │   ├── AllTasks.vue
│   ├── Executor/       # Страницы исполнителя
│   │   ├── AllTasks.vue
│   └── Shared/         # Общие страницы (например, Profile, Notifications)
│   │   └── Profile.vue
└── Utils/              # JS-утилиты (helpers, форматирование дат и т.п.)
```

---

## Примечания
- **Auth** — всегда выделяется отдельно, так как не зависит от роли.  
- **Dashboard** — точка входа для каждой роли.  
- **Shared** — общие страницы, доступные нескольким ролям.  
- Если в будущем страниц станет очень много, допускается группировка **по сущностям**, а внутри сущности — разделение по ролям (например, `Pages/Projects/Admin/`, `Pages/Projects/Manager/`).  
