// Both controls are bound the same way: the template emits a plain button with
// an id and this file attaches the behavior, so nothing here depends on an
// inline handler or on a CSP that allows 'unsafe-inline'.
const step = (delta) => {
    const output = document.querySelector("#output");

    output.innerText = +output.innerText + delta;
};

document.querySelector("#add")
    .addEventListener("click", () => step(1));

document.querySelector("#subtract")
    .addEventListener("click", () => step(-1));
