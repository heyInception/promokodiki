<?php

/**
 * AMP CSS
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      3.3.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/public/partials
 */
?>

.rmp-amp-results-widget .rmp-amp-results-widget__stars {
  display: inline;
}

.rmp-amp-results-widget .rmp-amp-results-widget__average-rating, .rmp-amp-results-widget .rmp-amp-results-widget__vote-count {
  display: inline-block;
  position: relative;
  bottom: 4px;
}

.rmp-amp-results-widget .rmp-icon::before {
  content: "★";
  font-size: 26px;
  color: #ccc;
  margin: -2px;
}

.rmp-amp-results-widget .rmp-icon {
  font-style: normal;
  font-size: 26px;
  line-height: 26px;
}

.rmp-amp-results-widget .rmp-icon--full-highlight::before {
  color: #FF912C;
}

.rmp-amp-results-widget .rmp-icon--half-highlight::before {
  background: -webkit-gradient(linear, left top, right top, color-stop(50%, #FF912C), color-stop(50%, #ccc));
  background: linear-gradient(to right, #FF912C 50%, #ccc 50%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.rmp-amp-rating-widget {
  text-align: center;
}

#rmp-amp-rating-widget__title {
  font-size: 1.8rem;
  margin-bottom: .5rem;
}

.rmp-amp-rating-widget .rmp-amp-rating-widget__subtitle {
    margin-bottom: 0;
}

#rmp-amp-action, #rmp-amp-post-id {
  display: none;
}

.rmp-amp-centered-fieldset {
  margin-left: auto;
  margin-right: auto;
  max-width: 100%;
}

.rmp-amp-rating-widget__fieldset {
  --star-size: 3;  /* use CSS variables to calculate dependent dimensions later */
  padding: 0;  /* to prevent flicker when mousing over padding */
  border: none;  /* to prevent flicker when mousing over border */
  unicode-bidi: bidi-override; direction: rtl;  /* for CSS-only style change on hover */
  user-select: none;  /* disable mouse/touch selection */
  font-size: 3em;  /* fallback - IE doesn't support CSS variables */
  font-size: calc(var(--star-size) * 1em);
  cursor: pointer;
  -webkit-tap-highlight-color: rgba(0,0,0,0);
  -webkit-tap-highlight-color: transparent;
  margin-bottom: 16px;
  line-height: normal;
  display:inline-block;
}

.rmp-amp-rating-widget__fieldset > label, .rmp-amp-rating-widget__fieldset > input[type=radio] + label {
  display: inline-block;
  position: relative;
  width: 1.1em;  /* magic number to overlap the radio buttons on top of the stars */
  width: calc(var(--star-size) / 3 * 1.1em);
  font-size:2rem;
}
.rmp-amp-rating-widget__fieldset > *:hover,
.rmp-amp-rating-widget__fieldset > *:hover ~ label,
.rmp-amp-rating-widget__fieldset:not(:hover) > input:checked ~ label {
  color: transparent;  /* reveal the contour/white star from the HTML markup */
  cursor: inherit;  /* avoid a cursor transition from arrow/pointer to text selection */
}
.rmp-amp-rating-widget__fieldset > *:hover:before,
.rmp-amp-rating-widget__fieldset > *:hover ~ label:before,
.rmp-amp-rating-widget__fieldset:not(:hover) > input:checked ~ label:before {
  content: "★";
  position: absolute;
  color: gold;
}
.rmp-amp-rating-widget__fieldset > input {
  position: relative;
  transform: scale(3);  /* make the radio buttons big; they don't inherit font-size */
  transform: scale(var(--star-size));
  /* the magic numbers below correlate with the font-size */
  top: -0.5em;  /* margin-top doesn't work */
  top: calc(var(--star-size) / 6 * -1em);
  margin-left: -2.5em;  /* overlap the radio buttons exactly under the stars */
  margin-left: calc(var(--star-size) / 6 * -5em);
  z-index: 2;  /* bring the button above the stars so it captures touches/clicks */
  opacity: 0;  /* comment to see where the radio buttons are */
  font-size: initial; /* reset to default */
}
form.amp-form-submit-error [submit-error] {
  color: red;
}

.rmp-amp-rating-widget__not-rated--hidden {
  display: none;
}

.rmp-amp-rating-widget__results--hidden {
  display: none;
}

label#rmp-amp-post-nonce {
    display: none;
}
