let activeNavigation;

const liveFilterTimers = new WeakMap();
const liveFilterRequests = new WeakMap();

function liveFilterQuery(form) {
    const parameters = new URLSearchParams();

    for (const [name, value] of new FormData(form)) {
        if (typeof value === "string" && value.trim() !== "") {
            parameters.append(name, value.trim());
        }
    }

    return parameters;
}

async function refreshLiveFilter(form, { pageUrl = null, addHistoryEntry = false } = {}) {
    const results = document.querySelector(form.dataset.liveFilterTarget);
    if (!results) {
        return;
    }

    liveFilterRequests.get(form)?.abort();
    const controller = new AbortController();
    liveFilterRequests.set(form, controller);

    const url = pageUrl ? new URL(pageUrl, window.location.href) : new URL(form.action, window.location.href);
    if (!pageUrl) {
        url.search = liveFilterQuery(form).toString();
    } else {
        for (const control of form.elements) {
            if (!control.name || !["search", "category", "status", "access_level", "typeFilter", "dateFilter"].includes(control.name)) {
                continue;
            }

            if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) {
                control.value = url.searchParams.get(control.name) ?? "";
            }
        }
    }

    const errorMessage = document.querySelector(form.dataset.liveFilterError);
    results.setAttribute("aria-busy", "true");
    results.classList.add("opacity-60");
    if (errorMessage) {
        errorMessage.textContent = "";
        errorMessage.classList.add("hidden");
    }

    try {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: {
                Accept: "text/html",
                "X-Live-Filter": "true",
            },
            signal: controller.signal,
        });
        if (!response.ok || !response.headers.get("content-type")?.includes("text/html")) {
            throw new Error("Unable to update the results. Please try again.");
        }

        const documentFragment = new DOMParser().parseFromString(await response.text(), "text/html");
        const incomingResults = documentFragment.querySelector(form.dataset.liveFilterTarget);
        if (!incomingResults) {
            throw new Error("The updated results could not be displayed.");
        }

        if (liveFilterRequests.get(form) !== controller) {
            return;
        }

        results.innerHTML = incomingResults.innerHTML;
        if (addHistoryEntry) {
            window.history.pushState({}, "", url);
        } else {
            window.history.replaceState({}, "", url);
        }
    } catch (error) {
        if (error.name !== "AbortError" && liveFilterRequests.get(form) === controller && errorMessage) {
            errorMessage.textContent = error.message;
            errorMessage.classList.remove("hidden");
        }
    } finally {
        if (liveFilterRequests.get(form) === controller) {
            results.removeAttribute("aria-busy");
            results.classList.remove("opacity-60");
        }
    }
}

document.addEventListener("input", (event) => {
    const input = event.target.closest("[data-live-filter-search]");
    if (!input) {
        return;
    }

    const form = input.form;
    clearTimeout(liveFilterTimers.get(form));
    liveFilterTimers.set(form, setTimeout(() => refreshLiveFilter(form), 400));
});

document.addEventListener("change", (event) => {
    const control = event.target.closest("[data-live-filter-control]");
    if (control?.form) {
        refreshLiveFilter(control.form);
    }
});

document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches("[data-live-filter-form]")) {
        return;
    }

    event.preventDefault();
    clearTimeout(liveFilterTimers.get(form));
    refreshLiveFilter(form);
});

document.addEventListener("click", (event) => {
    const pageLink = event.target.closest("[data-live-filter-results] a[href]");
    if (pageLink) {
        const form = document.querySelector(pageLink.closest("[data-live-filter-results]").dataset.liveFilterForm);
        if (form && new URL(pageLink.href, window.location.href).origin === window.location.origin) {
            event.preventDefault();
            refreshLiveFilter(form, { pageUrl: pageLink.href, addHistoryEntry: true });
        }

        return;
    }

    const exportLink = event.target.closest("[data-live-filter-export]");
    if (exportLink) {
        const form = document.querySelector(exportLink.dataset.liveFilterForm);
        if (form) {
            const url = new URL(exportLink.href, window.location.href);
            const format = url.searchParams.get("format");
            url.search = liveFilterQuery(form).toString();
            if (format) {
                url.searchParams.set("format", format);
            }
            exportLink.href = url;
        }
    }
});

async function navigateToPage(url, { addHistoryEntry = true } = {}) {
    const requestedUrl = new URL(url, window.location.href);

    if (requestedUrl.origin !== window.location.origin) {
        window.location.assign(requestedUrl.href);
        return;
    }

    activeNavigation?.abort();
    activeNavigation = new AbortController();

    try {
        const response = await fetch(requestedUrl, {
            credentials: "same-origin",
            headers: {
                Accept: "text/html",
                "X-Requested-With": "XMLHttpRequest",
            },
            signal: activeNavigation.signal,
        });

        if (
            !response.ok ||
            !response.headers.get("content-type")?.includes("text/html")
        ) {
            throw new Error("The destination cannot be loaded as an app page.");
        }

        const nextDocument = new DOMParser().parseFromString(
            await response.text(),
            "text/html",
        );
        const incomingMain = nextDocument.querySelector("[data-app-main]");
        const currentMain = document.querySelector("[data-app-main]");
        const currentHeader = document.querySelector("[data-app-header]");
        const currentSidebar = document.querySelector("[data-app-sidebar]");
        const currentHelp = document.querySelector("[data-app-help]");
        const incomingHeader = nextDocument.querySelector("[data-app-header]");
        const incomingSidebar =
            nextDocument.querySelector("[data-app-sidebar]");
        const incomingHelp = nextDocument.querySelector("[data-app-help]");

        if (
            !incomingMain ||
            !currentMain ||
            !incomingHeader ||
            !currentHeader ||
            !incomingSidebar ||
            !currentSidebar ||
            !incomingHelp ||
            !currentHelp
        ) {
            window.location.assign(response.url);
            return;
        }

        for (const pageScript of incomingMain.querySelectorAll("script")) {
            if (pageScript.src || pageScript.type === "module") {
                window.location.assign(response.url);
                return;
            }

            const executableScript = document.createElement("script");
            executableScript.textContent = pageScript.textContent;
            document.head.append(executableScript);
            executableScript.remove();
            pageScript.remove();
        }

        currentSidebar.replaceWith(document.importNode(incomingSidebar, true));
        currentHelp.replaceWith(document.importNode(incomingHelp, true));
        currentHeader.replaceWith(document.importNode(incomingHeader, true));
        currentMain.replaceWith(document.importNode(incomingMain, true));

        document.title = nextDocument.title;

        const nextCsrfToken = nextDocument.querySelector(
            'meta[name="csrf-token"]',
        )?.content;
        const currentCsrfToken = document.querySelector(
            'meta[name="csrf-token"]',
        );
        if (nextCsrfToken && currentCsrfToken) {
            currentCsrfToken.content = nextCsrfToken;
        }

        if (addHistoryEntry) {
            window.history.pushState({}, "", response.url);
        }

        const appState = window.Alpine?.$data(document.body);
        if (appState) {
            appState.sidebarOpen = false;
            appState.helpOpen = false;
        }

        document.querySelector("[data-app-main]")?.scrollTo(0, 0);
        document.dispatchEvent(
            new CustomEvent("app:navigated", { detail: { url: response.url } }),
        );
    } catch (error) {
        if (error.name !== "AbortError") {
            window.location.assign(requestedUrl.href);
        }
    }
}

document.addEventListener("click", (event) => {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const link = event.target.closest("a[href]");
    if (
        !link ||
        link.hasAttribute("download") ||
        (link.target && link.target !== "_self") ||
        link.hasAttribute("data-no-navigation")
    ) {
        return;
    }

    const destination = new URL(link.href, window.location.href);
    if (
        destination.origin !== window.location.origin ||
        (destination.protocol !== "http:" && destination.protocol !== "https:")
    ) {
        return;
    }

    if (
        destination.pathname === window.location.pathname &&
        destination.search === window.location.search &&
        destination.hash
    ) {
        return;
    }

    event.preventDefault();
    navigateToPage(destination.href);
});

document.addEventListener("submit", (event) => {
    const form = event.target;
    if (
        event.defaultPrevented ||
        !(form instanceof HTMLFormElement) ||
        form.method.toUpperCase() !== "GET" ||
        (form.target && form.target !== "_self")
    ) {
        return;
    }

    const destination = new URL(form.action, window.location.href);
    if (destination.origin !== window.location.origin) {
        return;
    }

    const parameters = destination.searchParams;
    for (const [name, value] of new FormData(form)) {
        if (typeof value !== "string") {
            return;
        }

        parameters.append(name, value);
    }

    if (event.submitter?.name) {
        parameters.append(event.submitter.name, event.submitter.value);
    }

    event.preventDefault();
    navigateToPage(destination.href);
});

window.addEventListener("popstate", () => {
    navigateToPage(window.location.href, { addHistoryEntry: false });
});
