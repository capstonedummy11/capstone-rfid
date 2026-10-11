# Repository Instructions for AI Coding Agents

## Database changes

After every database-related change, run the complete Laravel test suite:

```bash
php artisan test
```

A database-related change includes migrations, schema definitions, Eloquent models or relationships, factories, seeders, database configuration, query logic, persistence services, and controllers or commands that change stored data. Do not report a database-related task as complete unless the command passes. If the command cannot run, report the exact blocker and do not claim successful verification.

Keep the canonical database documentation synchronized with migrations:

- `docs/System Architecture/DATABASE_SCHEMA_REFERENCE.md`
- `docs/System Architecture/DATABASE_RELATIONSHIPS.md`
- `docs/System Architecture/DATABASE.md`

