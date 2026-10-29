// Turns each link marked { .asciicast } into a terminal player.
//
// The guide links to the .cast file as an ordinary Markdown link, so MkDocs
// checks and rewrites the path like any other link, in both languages. Without
// JavaScript, the link stays a plain download of the recording.
document.addEventListener("DOMContentLoaded", function () {
  if (typeof AsciinemaPlayer === "undefined") return;

  document.querySelectorAll("a.asciicast").forEach(function (link) {
    var src = link.getAttribute("href");

    // A link alone in its paragraph replaces the paragraph, so no empty <p>
    // is left behind to add space above the player.
    var parent = link.parentElement;
    var alone = parent.tagName === "P" && parent.textContent.trim() === link.textContent.trim();

    var figure = document.createElement("figure");
    figure.className = "asciicast-figure";
    var holder = document.createElement("div");
    var caption = document.createElement("figcaption");
    // The link text becomes the caption, keeping its `code` formatting.
    caption.append.apply(caption, Array.from(link.childNodes));
    figure.append(holder, caption);
    (alone ? parent : link).replaceWith(figure);

    AsciinemaPlayer.create(src, holder, {
      fit: "width",
      // Long pauses were already cut to 2 seconds when it was recorded.
      idleTimeLimit: 2,
      // The still frame shown before playing: the last one, so the reader sees
      // what a good result looks like without pressing play. A time past the
      // end shows the last frame. The recordings end on the prompt after the
      // result (see bin/dev/casts/scenarios/common.exp). A link can pick
      // another moment with data-poster="npt:M:SS".
      poster: link.dataset.poster || "npt:99:00",
    });
  });
});
