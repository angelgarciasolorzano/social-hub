---
paths:
  - 'app/**/Database/Factories/**/*.php'
---

# Factories

## Keep model factories complete with persisted model fields
When adding or changing a model factory, compare its default state with the model and backing migration/schema. Define realistic values or explicit nulls for every persisted field that should be represented by the factory; omit only fields Eloquent manages automatically (such as the primary key and timestamps). Update the factory whenever the model or schema changes.
