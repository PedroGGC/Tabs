# Performance Audit - blog-php

## Scope
This audit focuses on runtime hotspots without applying performance changes in this cycle.

Analyzed areas:
- Public post listing (`index.php`)
- User dashboard listing (`dashboard.php`)
- Single post read (`post.php`)
- Slug generation/lookup (`includes/functions.php`)
- Frontend navigation/transitions (`public/js/transitions.js`, `public/css/style.css`)

## Method and Environment
- Static code inspection of PHP, SQL, CSS, and JS.
- MySQL `EXPLAIN` executed against local database using the project connection.
- Current dataset is small (few rows), so row counts are low; access patterns still reveal index/sort behavior.

## Critical Query Map
1. Public listing:
```sql
SELECT posts.id, posts.title, posts.content, posts.created_at, users.username AS author
FROM posts
INNER JOIN users ON users.id = posts.user_id
ORDER BY posts.created_at DESC
```

2. Dashboard listing:
```sql
SELECT id, title, created_at, updated_at
FROM posts
WHERE user_id = :user_id
ORDER BY created_at DESC
```

3. Post details:
```sql
SELECT posts.id, posts.title, posts.content, posts.created_at, posts.updated_at, users.username AS author
FROM posts
INNER JOIN users ON users.id = posts.user_id
WHERE posts.id = :id
LIMIT 1
```

4. Slug checks:
```sql
SELECT id FROM posts WHERE slug = :slug LIMIT 1
SELECT id FROM posts WHERE slug = :slug AND id <> :id LIMIT 1
```

## EXPLAIN Evidence (Summary)

### 1) Public listing (`index.php`)
- `posts` access type: `ALL`
- key used: `null`
- extra: `Using filesort`

Interpretation:
- Unbounded scan + sort operation.
- As `posts` grows, this becomes a primary bottleneck.

### 2) Dashboard listing (`dashboard.php`)
- `posts` access type: `ref`
- key used: `idx_posts_user_id`
- extra: `Using filesort`

Interpretation:
- Filter on `user_id` is indexed.
- Sorting by `created_at DESC` still triggers filesort because no composite index matches `WHERE + ORDER BY`.

### 3) Post details (`post.php`)
- `posts`/`users` access type: `const`
- key used: `PRIMARY`

Interpretation:
- Good plan. This query is efficient and not a concern.

### 4) Slug lookups (`generateUniqueSlug`)
- `slug` query uses unique index (`key: slug`, `Extra: Using index`)
- ignore-id variant for same id returns `Impossible WHERE...` (expected for matching slug + excluded id)

Interpretation:
- Slug checks are efficient due to unique index.

## Frontend Performance Notes
1. Navigation transitions intentionally add `350ms` delay before route changes.
   - Improves UX continuity, but increases perceived latency on every navigation.
2. Many elements animate (`fadeUp`, page transitions, modal transitions).
   - Fine for small pages; on large tables this can increase paint/compositing work.
3. Public listing renders full `content` from DB then truncates in PHP.
   - More payload transferred from DB than necessary.

## Prioritized Recommendations (Not Applied)

## High Impact / Low-Medium Effort
1. Add composite index for dashboard query:
```sql
CREATE INDEX idx_posts_user_created ON posts(user_id, created_at);
```
Expected benefit:
- Reduces filesort on dashboard listing and improves scalability per user.

2. Add pagination to public and dashboard listings (`LIMIT/OFFSET`).
Expected benefit:
- Bounded query cost, bounded HTML payload, predictable response time.

3. Avoid selecting full `content` on index listing.
Expected benefit:
- Lower DB/network cost.
- Options:
  - Select fewer columns and keep excerpt generation from smaller text source.
  - Or store/use a summary field.

## Medium Impact
4. Add pagination indexes strategy review once pagination exists:
   - Evaluate keyset pagination (`WHERE created_at < :cursor`) for large datasets.

5. Revisit transition timing for high-frequency routes:
   - Keep slide transitions but reduce blocking delay on non-critical interactions if UX prioritizes speed.

## Low Impact
6. Audit repeated animation effects on table-heavy views and conditionally reduce motion where needed.

## Conclusion
- No severe DB bottleneck exists at current dataset size, but query plans already show scale risks (`Using filesort`) in list endpoints.
- Highest ROI next step: composite index + pagination.
- This cycle intentionally did not apply performance optimizations, only diagnosis.
