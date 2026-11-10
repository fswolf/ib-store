// EasyMDE (assets/vendor/easymde, fetched by update-libs.sh) on a textarea, with its toolbar drawn from inline svg so
// there is no icon font to ship. only the tools MarkdownLibrary renders on the store side. forceSync keeps the textarea
// filled, so the form around it reads it like any other field. preview(text, el) fills the preview, see admin/product.js.
// without the vendor files the textarea is left as it is
const icon = (paths) => `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

// shapes from lucide.dev (ISC licence)
const ICONS = {
    bold: '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>',
    italic: '<path d="M19 4h-9"/><path d="M14 20H5"/><path d="M15 4 9 20"/>',
    strikethrough: '<path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><path d="M4 12h16"/>',
    h2: '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M21 18h-4c0-4 4-3 4-6 0-1.5-2-2.5-4-1"/>',
    h3: '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17.5 10.5c1.7-1 3.5 0 3.5 1.5a2 2 0 0 1-2 2"/><path d="M17 17.5c2 1.5 4 .3 4-1.5a2 2 0 0 0-2-2"/>',
    list: '<path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>',
    numbered: '<path d="M10 6h11"/><path d="M10 12h11"/><path d="M10 18h11"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>',
    quote: '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>',
    line: '<path d="M5 12h14"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    preview: '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
    side: '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 3v18"/>',
    fullscreen: '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
};

export default class MarkdownEditor {
    constructor({ element, preview = null, minHeight = "320px" }) {
        const box = typeof element === "string" ? document.getElementById(element) : element;
        const E = window.EasyMDE;
        if (!box || !E) return;

        // the names are EasyMDE's own, so its shortcuts and the active button state still line up
        const tool = (name, action, title, shape, more = {}) => ({ name, action, title, icon: icon(ICONS[shape]), ...more });
        const view = { noDisable: true, noMobile: true }; // stay usable while previewing, like EasyMDE's own

        this.editor = new E({
            element: box,
            forceSync: true,
            autoDownloadFontAwesome: false,
            spellChecker: false,
            nativeSpellcheck: true,
            minHeight,
            status: ["lines", "words"],
            previewClass: ["editor-preview", "product-description"],
            previewRender: preview ? (text, el) => {
                preview(text, el);
                return el.innerHTML; // keeps the last render up while the new one comes
            } : undefined,
            toolbar: [
                tool("bold", E.toggleBold, "Bold", "bold"),
                tool("italic", E.toggleItalic, "Italic", "italic"),
                tool("strikethrough", E.toggleStrikethrough, "Strikethrough", "strikethrough"),
                "|",
                tool("heading-2", E.toggleHeading2, "Heading", "h2"),
                tool("heading-3", E.toggleHeading3, "Small heading", "h3"),
                "|",
                tool("unordered-list", E.toggleUnorderedList, "Bulleted list", "list"),
                tool("ordered-list", E.toggleOrderedList, "Numbered list", "numbered"),
                tool("quote", E.toggleBlockquote, "Quote", "quote"),
                tool("horizontal-rule", E.drawHorizontalRule, "Line", "line"),
                "|",
                tool("link", E.drawLink, "Link", "link"),
                "|",
                tool("preview", E.togglePreview, "Preview", "preview", { noDisable: true }),
                tool("side-by-side", E.toggleSideBySide, "Side by side", "side", view),
                tool("fullscreen", E.toggleFullScreen, "Fullscreen", "fullscreen", view),
            ],
        });
    }
}
