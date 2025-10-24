# TODO: Future Improvements

This file tracks planned improvements for the image tagging system. Items are organized by priority.

---

## High Priority (Implement Next)

### Tag Management API Endpoints
Add dedicated API endpoints for tag operations to provide comprehensive tag management for API clients.

**Endpoints to implement:**
- `POST /api/v1/images/{id}/tags/generate` - Regenerate AI tags for an image
- `POST /api/v1/images/{id}/tags` - Manually add tags to an image
- `DELETE /api/v1/images/{id}/tags` - Remove specific tags from an image
- `GET /api/v1/tags/popular` - Get most frequently used tags across user's images
- `GET /api/v1/tags/suggestions` - Suggest tags based on user's image history

**Benefits:**
- Gives API clients full control over tagging
- Enables manual tag curation and correction
- Provides analytics and insights for better UX

---

## Medium Priority

### Tag Validation Rules
Add comprehensive validation to ensure tag quality and prevent malformed data.

**Implementation:**
- Create `TagFormRequest` with validation rules
- Validate tag keys: alphanumeric + spaces/hyphens only, max 50 chars
- Validate tag values: alphanumeric + spaces/hyphens only, max 100 chars
- Prevent empty strings, excessive whitespace, special characters

**Example validation:**
```php
'key' => 'required|string|max:50|regex:/^[a-z0-9\s\-]+$/',
'value' => 'required|string|max:100|regex:/^[a-z0-9\s\-]+$/',
```

**Benefits:**
- Prevents data quality issues
- Ensures consistent tag format across the system
- Better error messages for API clients

---

### Error Handling & Logging
Add structured logging and better error handling for AI tag generation failures.

**Implementation:**
- Add try-catch with detailed logging in `TagService::generateTags()`
- Log Gemini API failures with context (image ID, user ID, error message)
- Add monitoring/alerting for repeated tag generation failures
- Track AI provider response times and confidence scores

**Example:**
```php
try {
    $response = $this->geminiProvider->callWithImage(...);
} catch (\Exception $e) {
    Log::error('Tag generation failed', [
        'image_id' => $image->id,
        'user_id' => $image->user_id,
        'error' => $e->getMessage(),
    ]);
    throw $e;
}
```

**Benefits:**
- Easier debugging of AI tagging issues
- Proactive detection of Gemini API problems
- Better insights into tagging success rates

---

## Low Priority

### Tag Management Features
Advanced features for managing and organizing tags at scale.

**Features to implement:**
- **Tag merging:** Consolidate similar tags (e.g., merge "Pokemon Card" into "pokemon card")
- **Bulk tag operations:** Replace all tags at once for an image
- **Tag cleanup:** Remove unused tags, merge duplicates
- **Tag aliases:** Map multiple tag values to canonical versions

**Benefits:**
- Maintain data quality as tag volume grows
- Reduce tag sprawl and improve consistency
- Better user experience with cleaner tag data

---

### Tag Analytics
Provide insights into tag usage and AI performance.

**Metrics to track:**
- Most frequently used tags across all users
- Tag confidence score distributions
- AI-generated vs user-provided tag ratios
- Popular tag combinations
- Tag growth over time

**Implementation ideas:**
- Dashboard showing top tags
- API endpoint: `GET /api/v1/stats/tags`
- Weekly email with tagging insights

**Benefits:**
- Understand how users interact with tagging
- Identify areas to improve AI prompts
- Product insights for feature prioritization

---

### Additional Database Indexes (Evaluate When Needed)
Additional indexes to consider if specific query patterns emerge.

**Potential indexes:**
- `image_tag(tag_id, source)` - Filter images by tag source (AI vs manual)
- `images.filename` (full-text) - If filename search becomes heavily used
- `image_tag(confidence)` - If filtering by confidence threshold

**When to add:**
- Monitor slow query logs
- Add indexes for queries taking >100ms
- Evaluate based on actual usage patterns

**Note:** Don't add these preemptively - wait for real usage data.

---

### AI Prompt Enhancements
Improve AI tagging quality and context awareness.

**Enhancements:**
- Include existing tags as context in prompts (avoid redundant tags)
- Improve multi-item detection prompts (clearer instructions for detecting multiple characters/objects)
- Experiment with different confidence threshold strategies
- Add domain-specific prompts (e.g., special handling for Pokemon cards, DVDs, books)

**Benefits:**
- Higher quality AI-generated tags
- Fewer duplicate/redundant tags
- Better multi-item detection accuracy

---

## Notes

- **Meilisearch/Scout integration:** Meilisearch handles its own indexing, no special tag handling needed
- **Scale considerations:** Current architecture supports 10K-100K images/user without issues
- **Performance monitoring:** Watch slow query logs before adding more indexes
- **Tag filtering:** When implementing tag filtering, verify if additional indexes are needed based on query patterns

---

_Last updated: 2025-10-24_
