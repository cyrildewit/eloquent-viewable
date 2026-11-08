---
title: "Recipes"
description: "Complete Laravel features built with Eloquent Viewable, from a trending sidebar to a seller's stats page, each with its full code and a Pest test."
sidebar:
  label: Overview
  order: 0
---

The rest of these docs cover each feature on its own. A recipe shows what they add up to:
complete features you would build in a real application, each one a few files you can read top to bottom and copy.

- [**Trending articles**](/eloquent-viewable/recipes/trending-articles/): a "Trending this week" sidebar with the ten articles taking off
  right now, recorded by middleware and ranked by views weighed by their age.
- [**Listing stats**](/eloquent-viewable/recipes/listing-stats/): a seller's stats page with a daily chart, unique visitors and the change
  against the 30 days before.
- [**Popular products**](/eloquent-viewable/recipes/popular-products/): a "Most viewed" sort that stays fast over millions of views,
  without slowing down the product page.
- [**Breaking news**](/eloquent-viewable/recipes/breaking-news/): a story that a hundred thousand readers open within minutes, buffered in
  Redis instead of hitting the database on every request.
- [**Recently viewed**](/eloquent-viewable/recipes/recently-viewed/): "Continue where you left off" and "Not opened yet" rows for a
  signed-in learner.
- [**Content dashboard**](/eloquent-viewable/recipes/content-dashboard/): one editors' page that answers "what worked this week" across
  guides and podcast episodes.
- [**Privacy-first analytics**](/eloquent-viewable/recipes/privacy-first-analytics/): counting readers of a docs site without cookies, IP
  addresses or a cookie banner.
- [**Author digest**](/eloquent-viewable/recipes/author-digest/): a Monday mail that tells every author how their writing did last week,
  in their own timezone.

Every sample comes with a Pest test that runs with the rest of the suite, so the code you copy is code that works.
