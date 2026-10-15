<?php

namespace App\Ai;

use App\Contacts\Contact;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $contact_id
 * @property int|null $product_id
 * @property string $status
 * @property string $source
 * @property string $question
 * @property string|null $context_snippet
 * @property string|null $contact_name
 * @property string|null $product_name
 * @property string|null $stage_name
 * @property string|null $lead_status
 * @property string|null $answer
 * @property string|null $lesson_category
 * @property int|null $article_id
 * @property int|null $answered_by_user_id
 * @property Carbon|null $answered_at
 */
#[Fillable([
    'lead_id',
    'contact_id',
    'product_id',
    'status',
    'source',
    'question',
    'context_snippet',
    'contact_name',
    'product_name',
    'stage_name',
    'lead_status',
    'answer',
    'lesson_category',
    'article_id',
    'answered_by_user_id',
    'answered_at',
])]
class KnowledgeGap extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<KnowledgeArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'article_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
