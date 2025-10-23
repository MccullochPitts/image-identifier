<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all tags with their normalized versions
        $tags = DB::table('tags')->get();

        // Group tags by normalized key+value
        $normalized = [];
        foreach ($tags as $tag) {
            $normalizedKey = strtolower(trim($tag->key));
            $normalizedValue = strtolower(trim($tag->value));
            $signature = $normalizedKey.'|'.$normalizedValue;

            if (! isset($normalized[$signature])) {
                $normalized[$signature] = [
                    'keep_id' => $tag->id,
                    'merge_ids' => [],
                    'key' => $normalizedKey,
                    'value' => $normalizedValue,
                ];
            } else {
                // This is a duplicate with different casing
                $normalized[$signature]['merge_ids'][] = $tag->id;
            }
        }

        // For each group with duplicates, merge them
        foreach ($normalized as $signature => $group) {
            if (! empty($group['merge_ids'])) {
                // 1. FIRST: Update image_tag pivot to point duplicates to the keeper
                DB::table('image_tag')
                    ->whereIn('tag_id', $group['merge_ids'])
                    ->update(['tag_id' => $group['keep_id']]);

                // 2. SECOND: Delete duplicate tags (removes unique constraint conflict)
                DB::table('tags')
                    ->whereIn('id', $group['merge_ids'])
                    ->delete();

                // 3. FINALLY: Update the keeper tag to normalized values (now safe)
                DB::table('tags')
                    ->where('id', $group['keep_id'])
                    ->update([
                        'key' => $group['key'],
                        'value' => $group['value'],
                    ]);
            } else {
                // No duplicates, just normalize the values
                DB::table('tags')
                    ->where('id', $group['keep_id'])
                    ->update([
                        'key' => $group['key'],
                        'value' => $group['value'],
                    ]);
            }
        }

        // Remove any duplicate pivot entries that may have been created during merge
        // Find duplicate pivot entries (same image_id and tag_id)
        $duplicates = DB::table('image_tag')
            ->select('image_id', 'tag_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('image_id', 'tag_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Delete all entries except the one with the lowest ID
            DB::table('image_tag')
                ->where('image_id', $duplicate->image_id)
                ->where('tag_id', $duplicate->tag_id)
                ->where('id', '>', $duplicate->keep_id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse normalization as we lose the original casing
    }
};
