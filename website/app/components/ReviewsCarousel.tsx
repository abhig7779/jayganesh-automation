import { reviewsFor, visibleReviews, type Topic } from "@/lib/reviews";
import ReviewsCarouselClient from "./ReviewsCarouselClient";

/**
 * Google reviews carousel. Picks reviews on the server so only the ones shown
 * reach the browser. With `topics`, shows reviews about that subject (e.g. seat
 * covers on the seat-covers page), topped up with the newest reviews if a topic
 * has only a few. `offset` rotates the list first, so sibling pages (the
 * dealer brand pages) each show a different set of reviews.
 */
export default function ReviewsCarousel({ topics, limit = 24, offset = 0 }: { topics?: Topic[]; limit?: number; offset?: number }) {
  let list = topics?.length ? reviewsFor(topics) : visibleReviews;
  if (list.length < 6) {
    const ids = new Set(list.map((r) => r.id));
    list = [...list, ...visibleReviews.filter((r) => !ids.has(r.id))];
  }
  if (offset && list.length > limit) {
    const k = offset % list.length;
    list = [...list.slice(k), ...list.slice(0, k)];
  }
  return <ReviewsCarouselClient reviews={list.slice(0, limit)} />;
}
