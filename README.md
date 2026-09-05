# Phoenix Agentic CRM 🦅

**Autonomous Agentic CRM for WordPress** — raw data ingestion, event tracking, proposals, goals, projects, and more.

Built for [Dynamix Systems](https://dynamixsystems.com) by Esmaeel Kargar.

---

## Features

- 🤖 **Autonomous Agent Pipeline** — Collect, process, decide, execute
- 📊 **Raw Data Ingestion** — Internal (WP-Statistics, EDD) + External (Google Trends, RSS, Competitors)
- 📝 **Proposal Management** — Create, approve, reject, archive with full audit trail
- 🎯 **Goal Tracking** — Strategic goals with time horizons (quarterly, yearly)
- 📁 **Project Management** — Link projects to proposals, track status
- 📋 **Event Log** — Complete audit trail with actor, action, target tracking
- 🔐 **API-First** — Full REST API with X-Phoenix-Key authentication
- 🖥️ **Admin Dashboard** — WordPress admin UI with all management interfaces

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+

## Installation

1. Download the latest release ZIP
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Navigate to the **Phoenix CRM** menu in your WordPress admin

## REST API

All endpoints require `X-Phoenix-Key` header authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/phoenix/v1/raw-data` | Get unsynced raw data |
| POST | `/phoenix/v1/raw-data/sync` | Mark raw data as synced |
| GET | `/phoenix/v1/proposals` | List proposals |
| POST | `/phoenix/v1/proposals` | Create proposal |
| POST | `/phoenix/v1/proposals/{id}/decide` | Approve/reject proposal |
| GET | `/phoenix/v1/goals` | List goals |
| POST | `/phoenix/v1/goals` | Create goal |
| GET | `/phoenix/v1/projects` | List projects |
| POST | `/phoenix/v1/projects` | Create project |
| GET | `/phoenix/v1/events` | List events |
| POST | `/phoenix/v1/events` | Create event |
| GET | `/phoenix/v1/summary` | Dashboard summary |

## Database Tables

The plugin creates 8 custom tables:
- `phoenix_raw_data` — Raw ingested data from any source
- `phoenix_events` — Audit trail / activity log
- `phoenix_proposals` — Decision proposals
- `phoenix_goals` — Strategic goals
- `phoenix_projects` — Concrete projects
- `phoenix_saved_items` — Bookmarks / saved links
- `phoenix_notes` — Free-form notes
- `phoenix_report_templates` — Report format definitions

## Development

```bash
# Clone the repo
git clone https://github.com/Esmaeel-Kargar/phoenix-agentic-crm.git

# The plugin is ready to use — just symlink or copy to wp-content/plugins/
```

## License

GPL v2 or later — see [LICENSE](LICENSE).

## Version History

### v1.0.0 (2026-09-05)
- Initial release
- Raw data collection (internal + external)
- Proposal management with approval workflow
- Goal and project tracking
- Event audit log
- Full REST API with authentication
- WordPress admin dashboard with 7 management pages