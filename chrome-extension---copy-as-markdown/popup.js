// Handle copy button.
document.getElementById("copyToClipboard").addEventListener("click", () => {
  const text = document.getElementById("outputText").value;
  navigator.clipboard.writeText(text).catch((err) => console.error(err));
});

// Handle paste button.
document.getElementById("pasteMarkdown").addEventListener("click", async () => {
  await loadPageSelectionOrClipboard();
  // Handle text conversion.
  const turndownService = new TurndownService();
  turndownService.addRule("underline", {
    filter: "u",
    replacement: function (content) {
      return `_${content}_`;
    },
  });

  turndownService.addRule("strikethrough", {
    filter: "del",
    replacement: function (content) {
      return `~~${content}~~`;
    },
  });

  const rawHtml = document.getElementById("source").innerHTML;
  let purified = DOMPurify.sanitize(rawHtml, { WHOLE_DOCUMENT: false });
  purified = promoteStyledSpansToTags(purified);
  purified = stripTopLevelBWrappers(purified);
  console.log("Purified HTML", purified);
  const markdown = turndownService.turndown(purified);
  document.getElementById("outputText").value = markdown;
});

// Handle paste button 2.
document.getElementById("pasteRichText").addEventListener("click", async () => {
  await loadPageSelectionOrClipboard();
  // Handle text conversion.
  const converter = new showdown.Converter();
  const markdown = document.getElementById("source").innerText;
  const html = converter.makeHtml(markdown);
  document.getElementById("outputText").value = html;
});

// Insert selection or clipboard.
async function loadPageSelectionOrClipboard() {
  const source = document.getElementById("source");
  source.innerHTML = ""; // Clear previous

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  const [{ result: selectionHTML }] = await chrome.scripting.executeScript({
    target: { tabId: tab.id },
    func: () => {
      const selection = window.getSelection();
      if (
        !selection ||
        selection.rangeCount === 0 ||
        selection.toString().trim() === ""
      ) {
        return "";
      }

      const range = selection.getRangeAt(0);
      const container = document.createElement("div");
      container.appendChild(range.cloneContents());
      return container.innerHTML;
    },
  });

  if (selectionHTML && selectionHTML.trim() !== "") {
    console.log("Selection HTML", selectionHTML);
    source.innerHTML = selectionHTML;
    return;
  }

  // Fallback: Try to read from clipboard
  try {
    const items = await navigator.clipboard.read();
    for (const item of items) {
      if (item.types.includes("text/html")) {
        const blob = await item.getType("text/html");
        const html = await blob.text();
        source.innerHTML = html;
        console.log("Clipboard HTML", html);
        return;
      } else if (item.types.includes("text/plain")) {
        const blob = await item.getType("text/plain");
        const text = await blob.text();
        source.innerText = text;
        console.log("Clipboard Text", text);
        return;
      }
    }
  } catch (err) {
    source.innerText = "Could not read clipboard. Paste manually.";
    console.warn(err);
  }
}

/***
 * Helper utilities.
 */
function promoteStyledSpansToTags(html) {
  const doc = new DOMParser().parseFromString(html, "text/html");
  const spans = doc.querySelectorAll("span");

  spans.forEach((span) => {
    const styleAttr = span.getAttribute("style") || "";
    const fontWeight = span.style.fontWeight?.trim();
    const numericWeight = parseInt(fontWeight, 10);

    let replacementTag = null;

    if (!isNaN(numericWeight) && numericWeight > 400) {
      replacementTag = "strong";
    } else if (styleAttr.includes("font-style:italic")) {
      replacementTag = "em";
    } else if (styleAttr.includes("text-decoration:underline")) {
      replacementTag = "u";
    } else if (styleAttr.includes("text-decoration:line-through")) {
      replacementTag = "del";
    } else {
      replacementTag = "span";
    }

    if (replacementTag) {
      const replacement = document.createElement(replacementTag);
      replacement.innerHTML = span.innerHTML;
      span.replaceWith(replacement);
    }
  });

  return doc.body.innerHTML;
}

function stripTopLevelBWrappers(html) {
  const doc = new DOMParser().parseFromString(html, "text/html");
  const body = doc.body;

  const firstChild = body.firstElementChild;
  if (firstChild && firstChild.tagName === "B") {
    firstChild.replaceWith(...firstChild.childNodes);
  }

  return body.innerHTML;
}
