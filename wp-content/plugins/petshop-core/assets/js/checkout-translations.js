(() => {
  const translations = window.petshopCheckoutTranslations || {};
  const entries = Object.entries(translations);
  if (!entries.length) return;

  const translateTextNodes = (root) => {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();
    while (node) {
      const text = node.nodeValue?.trim();
      if (text && translations[text]) {
        node.nodeValue = node.nodeValue.replace(text, translations[text]);
      }
      node = walker.nextNode();
    }
  };

  translateTextNodes(document.body);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          const text = node.nodeValue?.trim();
          if (text && translations[text]) {
            node.nodeValue = node.nodeValue.replace(text, translations[text]);
          }
          return;
        }
        if (node instanceof HTMLElement) translateTextNodes(node);
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
})();
