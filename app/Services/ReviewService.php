<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Riddle;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Services\Interfaces\ReviewServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class ReviewService implements ReviewServiceInterface
{
    protected $reviewRepository;

    public function __construct(ReviewRepositoryInterface $reviewRepository)
    {
        $this->reviewRepository = $reviewRepository;
    }

    public function getPaginatedReviews(Riddle $riddle, int $page, int $limit)
    {
        return $this->reviewRepository->getPaginatedByRiddle($riddle, $page, $limit);
    }

    public function getTopReviews(Riddle $riddle, int $limit)
    {
        return $this->reviewRepository->getTopByRiddle($riddle, $limit);
    }

    public function createReview(Riddle $riddle, int $userId, array $data)
    {
        if ($this->reviewRepository->userHasReviewedRiddle($riddle->id, $userId)) {
            throw new \Exception('Vous avez déjà laissé un avis pour cette énigme.', 403);
        }

        if (!$this->reviewRepository->userHasCompletedRiddle($riddle, $userId)) {
            throw new \Exception('Vous devez avoir terminé l\'énigme pour laisser un avis.', 422);
        }

        return $this->reviewRepository->create($riddle, $userId, $data);
    }

    public function updateReview(Review $review, array $data, int $userId)
    {
        if ($userId !== $review->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->reviewRepository->update($review, $data);
    }

    public function deleteReview(Review $review, int $userId)
    {
        if ($userId !== $review->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->reviewRepository->delete($review);
    }
}