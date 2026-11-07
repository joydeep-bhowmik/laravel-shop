<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use InvalidArgumentException;

class ReviewService
{
    /**
     * Submit a review. New reviews wait for moderation and do not affect ratings until approved.
     *
     * @throws InvalidArgumentException When the user has already reviewed this product.
     */
    public function submit(User $user, Product $product, int $rating, ?string $title, ?string $body): ProductReview
    {
        if (ProductReview::query()->where('product_id', $product->getKey())->where('user_id', $user->getKey())->exists()) {
            throw new InvalidArgumentException('You have already reviewed this product.');
        }

        return ProductReview::query()->create([
            'product_id' => $product->getKey(),
            'user_id' => $user->getKey(),
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'is_approved' => false,
        ]);
    }

    /**
     * Approve a review and refresh the product's cached rating.
     */
    public function approve(ProductReview $review): void
    {
        $review->update(['is_approved' => true]);
        $this->refreshRating($review->product);
    }

    /**
     * Delete a review and refresh the product's cached rating.
     */
    public function delete(ProductReview $review): void
    {
        $product = $review->product;
        $review->delete();
        $this->refreshRating($product);
    }

    /**
     * Recalculate the average and count from approved reviews only.
     */
    public function refreshRating(Product $product): void
    {
        $approved = ProductReview::query()->where('product_id', $product->getKey())->approved();

        $product->forceFill([
            'rating_average' => round((float) (clone $approved)->avg('rating'), 2),
            'rating_count' => (clone $approved)->count(),
        ])->save();
    }
}
