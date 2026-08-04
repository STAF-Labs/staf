# Content Dimensions Data Model

Статус: сделано.

## Current Decision

Content types are global, but filters/dimensions are not global.

The intended ownership chain is:

```text
games -> game_content_types -> dimensions -> dimension_values
```

`dimensions` belong directly to a concrete `game_content_type`. This means the same filter name can safely exist in different games or different content types with different values.

Example:

- `Game A + Mod -> Platform -> PC, PlayStation`
- `Game B + Mod -> Platform -> PC, Xbox, Switch`

These are separate `dimensions` rows even if the visible name is the same.

## Important Schema Notes

- `content_types` remain global reusable content type definitions.
- `game_content_types` connects a game with a global content type.
- `dimensions.game_content_type_id` is required and owns the dimension context.
- `dimensions.slug` is unique only within a `game_content_type`, not globally.
- `dimension_values.dimension_id` points to the concrete contextual dimension.
- The old `game_content_type_dimensions` pivot table was removed.

Do not rebuild CRUD/API around global dimensions plus a pivot table. New filter management should create and manage dimensions under a selected `game_content_type`.

## Implemented Backend

API dimensions реализован вложенным в конкретный game content type:

```text
GET    /games/{game}/content-types/{gameContentType}/dimensions
POST   /games/{game}/content-types/{gameContentType}/dimensions
PATCH  /games/{game}/content-types/{gameContentType}/dimensions/{dimension}
DELETE /games/{game}/content-types/{gameContentType}/dimensions/{dimension}
```

Dimension values управляются внутри принадлежащей им dimension.
