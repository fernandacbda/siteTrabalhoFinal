const authApiUrl = new URL("../api/auth.php", document.currentScript?.src || window.location.href).href;
let currentUserAuthenticated = false;
let loadedArticle = null;

async function requestAuth(action, data = {}) {
    const response = await fetch(authApiUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        credentials: "same-origin",
        body: JSON.stringify({ action, ...data })
    });

    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error("O servidor retornou uma resposta inválida.");
    }

    if (!response.ok) {
        throw new Error(result.message || "Não foi possível concluir a operação.");
    }

    return result;
}

async function uploadProfilePhoto(file) {
    const formData = new FormData();
    formData.append("action", "upload_photo");
    formData.append("photo", file);

    const response = await fetch(authApiUrl, {
        method: "POST",
        headers: { "Accept": "application/json" },
        credentials: "same-origin",
        body: formData
    });

    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error("O servidor retornou uma resposta inválida.");
    }

    if (!response.ok) {
        throw new Error(result.message || "Não foi possível enviar a foto.");
    }

    return result;
}

async function submitArticle(formData) {
    const response = await fetch(authApiUrl, {
        method: "POST",
        headers: { "Accept": "application/json" },
        credentials: "same-origin",
        body: formData
    });

    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error("O servidor retornou uma resposta inválida.");
    }

    if (!response.ok) {
        throw new Error(result.message || "Não foi possível salvar o artigo.");
    }

    return result;
}

function setFormMessage(element, message, type) {
    element.textContent = message;
    element.dataset.type = type;
}

function setupAuthForm(form, messageElement) {
    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('input[type="submit"]');
        const originalButtonText = submitButton.value;
        const formData = new FormData(form);
        const action = form.id === "signup-form" ? "register" : "login";

        if (action === "register") {
            const password = formData.get("senha");
            const confirmPassword = formData.get("senhaC");

            if (password !== confirmPassword) {
                form.elements.senhaC.setCustomValidity("As senhas não coincidem.");
                form.reportValidity();
                return;
            }

            form.elements.senhaC.setCustomValidity("");
        }

        const data = action === "register"
            ? {
                name: formData.get("nome"),
                email: formData.get("email"),
                password: formData.get("senha")
            }
            : {
                email: formData.get("email"),
                password: formData.get("senha")
            };

        submitButton.disabled = true;
        submitButton.value = action === "register" ? "Cadastrando..." : "Entrando...";
        setFormMessage(messageElement, "", "");

        try {
            await requestAuth(action, data);

            if (action === "login") {
                window.location.href = "dashboard.html";
                return;
            }

            window.location.href = "login.html?cadastro=sucesso";
        } catch (error) {
            const message = error instanceof TypeError
                ? "Não foi possível conectar ao servidor. Verifique se o PHP está ativo."
                : error.message;
            setFormMessage(messageElement, message, "error");
        } finally {
            submitButton.disabled = false;
            submitButton.value = originalButtonText;
        }
    });

    if (form.id === "signup-form") {
        form.elements.senhaC.addEventListener("input", () => {
            form.elements.senhaC.setCustomValidity("");
        });
    }
}

function updateMenu(authenticated) {
    const menu = document.querySelector(".menu_container ul");
    if (!menu) {
        return;
    }

    const links = authenticated
        ? [
            ["Artigos", "tela_principal.html"],
            ["Escrever Artigo", "escrever_artigo.html"],
            ["Perfil", "perfil_usuario.html"],
            ["Dashboard", "dashboard.html"]
        ]
        : [
            ["Cadastre-se", "signup.html"],
            ["Entrar", "login.html"]
        ];

    menu.replaceChildren();

    links.forEach(([label, href]) => {
        const item = document.createElement("li");
        const link = document.createElement("a");
        link.textContent = label;
        link.href = href;
        item.append(link);
        menu.append(item);
    });

    if (authenticated) {
        const item = document.createElement("li");
        const link = document.createElement("a");
        link.href = "#";
        link.textContent = "Sair";
        link.addEventListener("click", async (event) => {
            event.preventDefault();
            link.setAttribute("aria-disabled", "true");

            try {
                await requestAuth("logout");
                window.location.href = "index.html";
            } catch (error) {
                link.removeAttribute("aria-disabled");
                console.error(error);
                window.alert(error.message);
            }
        });
        item.append(link);
        menu.append(item);
    }
}

const loginForm = document.querySelector("#login-form");
const signupForm = document.querySelector("#signup-form");

if (loginForm) {
    const message = document.querySelector("#auth-message");
    const params = new URLSearchParams(window.location.search);

    if (params.get("cadastro") === "sucesso") {
        setFormMessage(message, "Cadastro realizado. Entre com seu e-mail e senha.", "success");
    }

    setupAuthForm(loginForm, message);
}

if (signupForm) {
    setupAuthForm(signupForm, document.querySelector("#auth-message"));
}

const profileForm = document.querySelector("#profile-form");
if (profileForm) {
    const profileMessage = document.querySelector("#profile-message");

    profileForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        const submitButton = profileForm.querySelector('input[type="submit"]');
        const originalButtonText = submitButton.value;
        const formData = new FormData(profileForm);

        submitButton.disabled = true;
        submitButton.value = "Salvando...";
        setFormMessage(profileMessage, "", "");

        try {
            const result = await requestAuth("update_profile", {
                name: formData.get("name"),
                email: formData.get("email"),
                bio: formData.get("bio")
            });

            const profileName = document.querySelector(".perfil_dados h2");
            const profileEmail = document.querySelector(".perfil_dados > p:not(.data_cadastro):not(.bio)");
            const profileBio = document.querySelector(".perfil_dados .bio");

            profileName.textContent = result.user.name;
            profileEmail.textContent = result.user.email;
            profileBio.textContent = result.user.bio || "Biografia ainda não informada.";
            setFormMessage(profileMessage, result.message, "success");
        } catch (error) {
            const message = error instanceof TypeError
                ? "Não foi possível conectar ao servidor. Verifique se o PHP está ativo."
                : error.message;
            setFormMessage(profileMessage, message, "error");
        } finally {
            submitButton.disabled = false;
            submitButton.value = originalButtonText;
        }
    });
}

const articleForm = document.querySelector("#article-form");
if (articleForm) {
    const articleMessage = document.querySelector("#article-message");
    const articleStatus = document.querySelector("#article-status");
    const coverInput = document.querySelector("#article-cover");
    const coverName = document.querySelector("#cover-name");
    const coverPreviewWrap = document.querySelector("#cover-preview-wrap");
    const coverPreview = document.querySelector("#cover-preview");
    let coverPreviewUrl = null;

    const clearCoverSelection = () => {
        coverInput.value = "";
        coverName.textContent = "Escolher imagem";
        coverPreview.removeAttribute("src");
        coverPreviewWrap.hidden = true;
        if (coverPreviewUrl) {
            URL.revokeObjectURL(coverPreviewUrl);
            coverPreviewUrl = null;
        }
    };

    articleForm.querySelectorAll("button[type='submit'][data-status]").forEach((button) => {
        button.addEventListener("click", () => {
            articleStatus.value = button.dataset.status;
        });
    });

    const summaryInput = articleForm.elements.summary;
    const contentInput = articleForm.elements.content;
    summaryInput.addEventListener("input", () => {
        document.querySelector("#summary-count").textContent = Array.from(summaryInput.value).length;
    });
    contentInput.addEventListener("input", () => {
        document.querySelector("#content-count").textContent = Array.from(contentInput.value).length;
    });

    coverInput.addEventListener("change", () => {
        const [file] = coverInput.files;
        if (!file) {
            return;
        }

        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
            setFormMessage(articleMessage, "Escolha uma imagem JPG, PNG ou WebP.", "error");
            clearCoverSelection();
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            setFormMessage(articleMessage, "A imagem de capa deve ter no máximo 2 MB.", "error");
            clearCoverSelection();
            return;
        }

        if (coverPreviewUrl) {
            URL.revokeObjectURL(coverPreviewUrl);
        }
        coverPreviewUrl = URL.createObjectURL(file);
        coverPreview.src = coverPreviewUrl;
        coverName.textContent = file.name;
        coverPreviewWrap.hidden = false;
        setFormMessage(articleMessage, "", "");
    });

    document.querySelector("#cover-remove").addEventListener("click", () => {
        clearCoverSelection();
    });

    articleForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (event.submitter?.dataset.status) {
            articleStatus.value = event.submitter.dataset.status;
        }

        const buttons = [...articleForm.querySelectorAll("button[type='submit']")];
        buttons.forEach((button) => {
            button.disabled = true;
            button.textContent = button.dataset.status === articleStatus.value
                ? (articleStatus.value === "published" ? "Publicando..." : "Salvando...")
                : button.textContent;
        });
        setFormMessage(articleMessage, "", "");

        try {
            const formData = new FormData(articleForm);
            formData.append("action", "create_article");
            const result = await submitArticle(formData);
            window.location.href = articleStatus.value === "published"
                ? `artigo.html?id=${encodeURIComponent(result.article.id)}`
                : "dashboard.html";
        } catch (error) {
            const message = error instanceof TypeError
                ? "Não foi possível conectar ao servidor. Verifique se o PHP está ativo."
                : error.message;
            setFormMessage(articleMessage, message, "error");
        } finally {
            buttons.forEach((button) => {
                button.disabled = false;
                button.textContent = button.dataset.status === "published"
                    ? "Publicar artigo"
                    : "Salvar rascunho";
            });
        }
    });
}

const profilePhotoInput = document.querySelector("#perfil-foto");
if (profilePhotoInput) {
    const photoMessage = document.querySelector("#perfil-foto-mensagem");
    const profilePhoto = document.querySelector(".perfil_foto");

    profilePhotoInput.addEventListener("change", async () => {
        const [file] = profilePhotoInput.files;
        if (!file) {
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            setFormMessage(photoMessage, "A imagem deve ter no máximo 2 MB.", "error");
            profilePhotoInput.value = "";
            return;
        }

        setFormMessage(photoMessage, "Enviando foto...", "");
        profilePhotoInput.disabled = true;

        try {
            const result = await uploadProfilePhoto(file);
            profilePhoto.src = `${result.profile_photo}?v=${Date.now()}`;
            setFormMessage(photoMessage, result.message, "success");
        } catch (error) {
            const message = error instanceof TypeError
                ? "Não foi possível conectar ao servidor. Verifique se o PHP está ativo."
                : error.message;
            setFormMessage(photoMessage, message, "error");
        } finally {
            profilePhotoInput.disabled = false;
            profilePhotoInput.value = "";
        }
    });
}

const dashboardNewArticle = document.querySelector("#dashboard-new-article");
if (dashboardNewArticle) {
    dashboardNewArticle.addEventListener("click", () => {
        window.location.href = "escrever_artigo.html";
    });
}

function formatArticleDate(dateTime) {
    const date = new Date(`${dateTime.replace(" ", "T")}Z`);
    if (Number.isNaN(date.getTime())) {
        return "Data indisponível";
    }

    return new Intl.DateTimeFormat("pt-BR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
    }).format(date);
}

function formatRating(value) {
    return Number(value || 0).toLocaleString("pt-BR", {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    });
}

function createRatingStars(value, className = "st_container") {
    const stars = document.createElement("div");
    stars.className = className;
    stars.setAttribute("aria-hidden", "true");
    const filledStars = Math.round(Number(value) || 0);

    for (let index = 1; index <= 5; index += 1) {
        const star = document.createElement("img");
        star.src = index <= filledStars ? "../img/staron.png" : "../img/staroff.png";
        star.alt = "";
        stars.append(star);
    }

    return stars;
}

function createRatingSummary(average, count) {
    const summary = document.createElement("div");
    summary.className = "rating_summary";
    summary.append(createRatingStars(average));

    const value = document.createElement("span");
    const ratingCount = Number(count) || 0;
    value.textContent = ratingCount === 0
        ? "0,0 · Sem avaliações"
        : `${formatRating(average)} · ${ratingCount} ${ratingCount === 1 ? "avaliação" : "avaliações"}`;
    summary.append(value);
    return summary;
}

function updateOwnerRatingSummary(articles) {
    const summaryElement = document.querySelector(".average-rating");
    if (!summaryElement) {
        return;
    }

    const publishedArticles = articles.filter((article) => article.status === "published");
    const ratingCount = publishedArticles.reduce((total, article) => total + Number(article.rating_count || 0), 0);
    const ratingTotal = publishedArticles.reduce(
        (total, article) => total + Number(article.rating_average || 0) * Number(article.rating_count || 0),
        0
    );
    const average = ratingCount === 0 ? 0 : ratingTotal / ratingCount;
    summaryElement.replaceChildren(createRatingSummary(average, ratingCount));
}

function renderRecentRatings(ratings) {
    const list = document.querySelector(".lista_avaliacoes");
    if (!list) {
        return;
    }

    list.replaceChildren();
    if (ratings.length === 0) {
        const empty = document.createElement("li");
        empty.className = "rating_empty";
        empty.textContent = "Ainda não há avaliações para seus artigos.";
        list.append(empty);
        return;
    }

    ratings.forEach((rating) => {
        const item = document.createElement("li");
        const description = document.createElement("p");
        const articleLink = document.createElement("a");
        articleLink.href = `artigo.html?id=${encodeURIComponent(rating.article_id)}`;
        articleLink.textContent = rating.article_title;
        description.append(
            document.createTextNode(`${rating.reviewer_name} avaliou o artigo `),
            articleLink
        );

        const stars = createRatingStars(rating.rating, "estrelas");
        const score = document.createElement("p");
        score.className = "rating_given";
        score.textContent = `${rating.rating} de 5 estrelas`;
        const date = document.createElement("p");
        date.className = "data_avaliacao";
        date.textContent = formatArticleDate(rating.updated_at);
        item.append(description, stars, score, date);
        list.append(item);
    });
}

function createArticleCard(article) {
    const card = document.createElement("a");
    card.className = "artigo_card";
    card.href = `artigo.html?id=${encodeURIComponent(article.id)}`;
    card.setAttribute("aria-label", `Abrir artigo: ${article.title}`);

    if (article.cover_image) {
        const image = document.createElement("img");
        image.src = article.cover_image;
        image.alt = `Capa do artigo: ${article.title}`;
        card.append(image);
    }

    const title = document.createElement("h3");
    title.textContent = article.title;

    const summary = document.createElement("p");
    summary.textContent = article.summary;
    const date = document.createElement("p");
    date.className = "data_pub";
    date.textContent = article.status === "published"
        ? `Publicado em ${formatArticleDate(article.created_at)}`
        : "Rascunho";

    card.append(title, summary);
    if (article.status === "published") {
        card.append(createRatingSummary(article.rating_average, article.rating_count));
    }
    card.append(date);
    return card;
}

function createPublicArticleCard(article) {
    const card = document.createElement("article");
    card.className = "article_card";

    if (article.cover_image) {
        const image = document.createElement("img");
        image.src = article.cover_image;
        image.alt = `Capa do artigo: ${article.title}`;
        card.append(image);
    } else {
        const placeholder = document.createElement("div");
        placeholder.className = "article_cover_placeholder";
        placeholder.setAttribute("aria-hidden", "true");
        card.append(placeholder);
    }

    const details = document.createElement("div");
    const title = document.createElement("h3");
    const titleLink = document.createElement("a");
    titleLink.href = `artigo.html?id=${encodeURIComponent(article.id)}`;
    titleLink.textContent = article.title;
    title.append(titleLink);
    const author = document.createElement("p");
    author.textContent = `${article.author_name} · ${formatArticleDate(article.created_at)}`;
    const summary = document.createElement("p");
    summary.textContent = article.summary;
    const rating = createRatingSummary(article.rating_average, article.rating_count);
    const link = document.createElement("a");
    link.className = "article_read_link";
    link.href = `artigo.html?id=${encodeURIComponent(article.id)}`;
    link.textContent = "Leia Mais";
    details.append(title, author, rating, summary, link);
    card.append(details);
    return card;
}

function createHomepageArticleCard(article) {
    const card = document.createElement("a");
    card.className = "home_article_card";
    card.href = `artigo.html?id=${encodeURIComponent(article.id)}`;
    card.setAttribute("aria-label", `Abrir artigo: ${article.title}`);

    if (article.cover_image) {
        const image = document.createElement("img");
        image.src = article.cover_image;
        image.alt = "";
        card.append(image);
    } else {
        const placeholder = document.createElement("div");
        placeholder.className = "home_article_cover_placeholder";
        placeholder.setAttribute("aria-hidden", "true");
        card.append(placeholder);
    }

    const title = document.createElement("h3");
    title.textContent = article.title;
    const author = document.createElement("p");
    author.className = "home_article_author";
    author.textContent = `${article.author_name} · ${formatArticleDate(article.created_at)}`;
    const summary = document.createElement("p");
    summary.textContent = article.summary;
    card.append(title, author, createRatingSummary(article.rating_average, article.rating_count), summary);
    return card;
}

async function loadPublicArticles() {
    const articleGrids = document.querySelectorAll(".article_grid");
    if (articleGrids.length === 0) {
        return;
    }

    const isHomepage = [...articleGrids].some((grid) => grid.classList.contains("homepage_articles"));
    const orderSelect = document.querySelector(".sc_form select[name='ordem']");
    const sort = isHomepage ? "recentes" : (orderSelect?.value || "avaliacao");
    const query = new URLSearchParams({
        action: "public_articles",
        source: isHomepage ? "homepage" : "listing",
        sort
    });
    const response = await fetch(`${authApiUrl}?${query}`, {
        headers: { "Accept": "application/json" }
    });
    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error("O servidor retornou uma resposta inválida.");
    }
    if (!response.ok) {
        throw new Error(result.message || "Não foi possível carregar os artigos publicados.");
    }

    articleGrids.forEach((grid) => {
        grid.replaceChildren();
        if (result.articles.length === 0) {
            const emptyMessage = document.createElement("p");
            emptyMessage.className = "articles_empty";
            emptyMessage.textContent = "Ainda não há artigos publicados.";
            grid.append(emptyMessage);
            return;
        }

        result.articles.forEach((article) => {
            const card = grid.classList.contains("homepage_articles")
                ? createHomepageArticleCard(article)
                : createPublicArticleCard(article);
            grid.append(card);
        });
    });

    const listMessage = document.querySelector("#public-articles-message");
    if (listMessage) {
        listMessage.textContent = "";
    }
}

const articleSortForm = document.querySelector(".sc_form");
if (articleSortForm) {
    const reloadSortedArticles = () => {
        loadPublicArticles().catch((error) => {
            console.error(error);
            const grid = document.querySelector(".article_grid:not(.homepage_articles)");
            if (grid) {
                const errorMessage = document.createElement("p");
                errorMessage.className = "articles_empty";
                errorMessage.textContent = "Não foi possível ordenar os artigos. Atualize a página e tente novamente.";
                grid.replaceChildren(errorMessage);
            }
        });
    };

    articleSortForm.querySelector("select[name='ordem']")?.addEventListener("change", reloadSortedArticles);
    articleSortForm.addEventListener("submit", (event) => {
        event.preventDefault();
        reloadSortedArticles();
    });
}

function renderDashboardArticles(articles) {
    const tableBody = document.querySelector("#dashboard-articles");
    if (!tableBody) {
        return;
    }

    tableBody.replaceChildren();
    if (articles.length === 0) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = 6;
        cell.textContent = "Você ainda não escreveu nenhum artigo.";
        row.append(cell);
        tableBody.append(row);
    } else {
        articles.forEach((article) => {
            const row = document.createElement("tr");
            const titleCell = document.createElement("td");
            const titleLink = document.createElement("a");
            titleLink.href = `artigo.html?id=${encodeURIComponent(article.id)}`;
            titleLink.textContent = article.title;
            titleCell.append(titleLink);

            const dateCell = document.createElement("td");
            dateCell.textContent = article.status === "published"
                ? formatArticleDate(article.created_at)
                : "-";

            const ratingCell = document.createElement("td");
            ratingCell.textContent = article.status === "published"
                ? `${formatRating(article.rating_average)} (${Number(article.rating_count) || 0})`
                : "-";
            const viewsCell = document.createElement("td");
            viewsCell.textContent = "-";

            const statusCell = document.createElement("td");
            const status = document.createElement("span");
            status.className = `status ${article.status === "published" ? "status_publicado" : "status_rascunho"}`;
            status.textContent = article.status === "published" ? "Publicado" : "Rascunho";
            statusCell.append(status);

            const actionsCell = document.createElement("td");
            const articleLink = document.createElement("a");
            articleLink.className = "btn_editar";
            articleLink.href = `artigo.html?id=${encodeURIComponent(article.id)}`;
            articleLink.textContent = article.status === "published" ? "Visualizar" : "Ver rascunho";
            actionsCell.append(articleLink);
            row.append(titleCell, dateCell, ratingCell, viewsCell, statusCell, actionsCell);
            tableBody.append(row);
        });
    }

    const stats = document.querySelectorAll(".dashboard_stats .stat_card p");
    if (stats.length >= 2) {
        stats[0].textContent = articles.filter((article) => article.status === "published").length;
        stats[1].textContent = articles.filter((article) => article.status === "draft").length;
    }
}

async function loadMyArticles() {
    const profileGrid = document.querySelector(".perfil_content .artigos_grid");
    const dashboardTable = document.querySelector("#dashboard-articles");
    if (!profileGrid && !dashboardTable) {
        return;
    }

    const response = await fetch(`${authApiUrl}?action=my_articles`, {
        headers: { "Accept": "application/json" },
        credentials: "same-origin"
    });
    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error("O servidor retornou uma resposta inválida.");
    }
    if (!response.ok) {
        throw new Error(result.message || "Não foi possível carregar seus artigos.");
    }

    const articles = result.articles;
    if (profileGrid) {
        profileGrid.replaceChildren();
        const articleMessage = document.querySelector("#article-list-message");
        if (articleMessage) {
            articleMessage.hidden = true;
            articleMessage.textContent = "";
        }
        if (articles.length === 0) {
            const emptyMessage = document.createElement("p");
            emptyMessage.className = "articles_empty";
            emptyMessage.textContent = "Você ainda não escreveu nenhum artigo.";
            profileGrid.append(emptyMessage);
        } else {
            articles.forEach((article) => profileGrid.append(createArticleCard(article)));
        }
    }
    renderDashboardArticles(articles);
    updateOwnerRatingSummary(articles);
    renderRecentRatings(result.recent_ratings || []);

    const publishedCount = articles.filter((article) => article.status === "published").length;
    const profileArticleCount = document.querySelector(".perfil_resumo .stat_card p");
    if (profileArticleCount) {
        profileArticleCount.textContent = publishedCount;
    }
}

const articleLoadMessage = document.querySelector("#article-load-message");
const requestedArticleId = new URLSearchParams(window.location.search).get("id");
const articleRatingPanel = document.querySelector("#article-rating");

function renderArticleRating(article) {
    if (!articleRatingPanel || !article) {
        return;
    }

    const summary = document.querySelector("#article-rating-summary");
    const controls = document.querySelector("#article-rating-controls");
    const message = document.querySelector("#rating-message");
    summary.replaceChildren(createRatingSummary(article.rating_average, article.rating_count));
    controls.replaceChildren();
    message.textContent = "";
    articleRatingPanel.hidden = false;

    if (article.status !== "published") {
        message.textContent = "Artigos em rascunho ainda não podem ser avaliados.";
        return;
    }

    if (!currentUserAuthenticated) {
        const prompt = document.createElement("p");
        const loginLink = document.createElement("a");
        loginLink.href = "login.html";
        loginLink.textContent = "Entre na sua conta";
        prompt.append(loginLink, document.createTextNode(" para avaliar este artigo."));
        controls.append(prompt);
        return;
    }

    const prompt = document.createElement("p");
    prompt.textContent = article.my_rating
        ? `Sua avaliação: ${article.my_rating} de 5 estrelas. Você pode alterá-la.`
        : "Sua avaliação";
    const stars = document.createElement("div");
    stars.className = "rating_controls";
    stars.setAttribute("role", "group");
    stars.setAttribute("aria-label", "Avalie este artigo de 1 a 5 estrelas");

    for (let value = 1; value <= 5; value += 1) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "rating_star";
        button.setAttribute("aria-label", `Avaliar com ${value} ${value === 1 ? "estrela" : "estrelas"}`);
        button.setAttribute("aria-pressed", Number(article.my_rating) === value ? "true" : "false");
        const image = document.createElement("img");
        image.src = value <= Number(article.my_rating || 0) ? "../img/staron.png" : "../img/staroff.png";
        image.alt = "";
        button.append(image);
        button.addEventListener("click", async () => {
            const ratingMessage = document.querySelector("#rating-message");
            const ratingButtons = stars.querySelectorAll("button");
            ratingButtons.forEach((ratingButton) => {
                ratingButton.disabled = true;
            });
            ratingMessage.textContent = "Salvando sua avaliação...";
            ratingMessage.dataset.type = "";

            try {
                const result = await requestAuth("rate_article", {
                    article_id: Number(article.id),
                    rating: value
                });
                article.my_rating = result.rating;
                article.rating_average = result.rating_average;
                article.rating_count = result.rating_count;
                renderArticleRating(article);
                const updatedMessage = document.querySelector("#rating-message");
                updatedMessage.textContent = result.message;
                updatedMessage.dataset.type = "success";
            } catch (error) {
                ratingMessage.textContent = error instanceof TypeError
                    ? "Não foi possível conectar ao servidor. Verifique se o PHP está ativo."
                    : error.message;
                ratingMessage.dataset.type = "error";
                ratingButtons.forEach((ratingButton) => {
                    ratingButton.disabled = false;
                });
            }
        });
        stars.append(button);
    }

    controls.append(prompt, stars);
}

if (articleLoadMessage && articleRatingPanel) {
    if (!requestedArticleId) {
        articleLoadMessage.textContent = "Artigo não encontrado. Acesse um artigo publicado pela lista.";
        articleLoadMessage.dataset.type = "error";
    } else {
        fetch(`${authApiUrl}?action=article&id=${encodeURIComponent(requestedArticleId)}`, {
            headers: { "Accept": "application/json" },
            credentials: "same-origin"
        })
            .then(async (response) => {
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || "Não foi possível carregar o artigo.");
                }
                return result.article;
            })
            .then((article) => {
                loadedArticle = article;
                document.querySelector(".artigo_header .titulo").textContent = article.title;
                document.querySelector(".artigo_header .autor").textContent = `Por ${article.author_name}`;
                document.querySelector(".artigo_header .data_pub").textContent =
                    `${article.status === "published" ? "Publicado" : "Rascunho"} em ${formatArticleDate(article.created_at)}`;
                document.querySelector(".artigo_resumo").textContent = article.summary;
                document.querySelector(".artigo_text > div").textContent = article.content;
                const cover = document.querySelector(".artigo_header > img");
                if (article.cover_image) {
                    cover.src = article.cover_image;
                    cover.alt = `Capa do artigo: ${article.title}`;
                    cover.hidden = false;
                } else {
                    cover.hidden = true;
                }
                articleLoadMessage.hidden = true;
                renderArticleRating(article);
            })
            .catch((error) => {
                articleLoadMessage.textContent = error.message;
                articleLoadMessage.hidden = false;
                articleLoadMessage.dataset.type = "error";
            });
    }
}

const protectedPages = ["/dashboard.html", "/perfil_usuario.html", "/escrever_artigo.html"];
const isProtectedPage = protectedPages.some((path) => window.location.pathname.endsWith(path));

loadPublicArticles().catch((error) => {
    console.error(error);
    const message = document.querySelector("#public-articles-message");
    if (message) {
        message.textContent = "Não foi possível carregar os artigos. Atualize a página e tente novamente.";
        return;
    }

    document.querySelectorAll(".homepage_articles").forEach((grid) => {
        const errorMessage = document.createElement("p");
        errorMessage.className = "articles_empty";
        errorMessage.textContent = "Não foi possível carregar os artigos. Atualize a página e tente novamente.";
        grid.replaceChildren(errorMessage);
    });
});

fetch(`${authApiUrl}?action=session`, {
    headers: { "Accept": "application/json" },
    credentials: "same-origin"
})
    .then(async (response) => {
        if (!response.ok) {
            throw new Error("Não foi possível verificar a sessão.");
        }
        return response.json();
    })
    .then((result) => {
        currentUserAuthenticated = result.authenticated;
        updateMenu(result.authenticated);
        if (loadedArticle) {
            renderArticleRating(loadedArticle);
        }

        if (!result.authenticated) {
            if (isProtectedPage) {
                window.location.href = "login.html";
            }
            return;
        }

        const greeting = document.querySelector(".dashboard_header p");
        if (greeting) {
            greeting.textContent = `Olá, ${result.user.name}! Aqui você acompanha seus artigos e avaliações.`;
        }

        const profileName = document.querySelector(".perfil_dados h2");
        if (profileName) {
            profileName.textContent = result.user.name;
        }

        const profileEmail = document.querySelector(".perfil_dados p");
        if (profileEmail) {
            profileEmail.textContent = result.user.email;
        }

        const profileBio = document.querySelector(".perfil_dados .bio");
        if (profileBio) {
            profileBio.textContent = result.user.bio || "Biografia ainda não informada.";
        }

        const profileForm = document.querySelector("#profile-form");
        if (profileForm) {
            profileForm.elements.name.value = result.user.name;
            profileForm.elements.email.value = result.user.email;
            profileForm.elements.bio.value = result.user.bio || "";
        }

        const profilePhoto = document.querySelector(".perfil_foto");
        if (profilePhoto && result.user.profile_photo) {
            profilePhoto.src = result.user.profile_photo;
        }

        const registrationDate = document.querySelector(".data_cadastro");
        if (registrationDate && result.user.created_at) {
            const date = new Date(`${result.user.created_at.replace(" ", "T")}Z`);
            if (Number.isNaN(date.getTime())) {
                throw new Error("Não foi possível interpretar a data de cadastro.");
            }

            const formattedDate = new Intl.DateTimeFormat("pt-BR", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric"
            }).format(date);
            registrationDate.textContent = `Membro desde: ${formattedDate}`;
        }

        loadMyArticles().catch((error) => {
            console.error(error);
            const articleMessage = document.querySelector("#article-list-message");
            if (articleMessage) {
                articleMessage.textContent = "Não foi possível carregar seus artigos. Atualize a página e tente novamente.";
                articleMessage.hidden = false;
            }
            const dashboardTable = document.querySelector("#dashboard-articles");
            if (dashboardTable) {
                dashboardTable.replaceChildren();
                const row = document.createElement("tr");
                const cell = document.createElement("td");
                cell.colSpan = 6;
                cell.textContent = "Não foi possível carregar seus artigos. Atualize a página e tente novamente.";
                row.append(cell);
                dashboardTable.append(row);
            }
        });
    })
    .catch((error) => {
        console.error(error);
        if (isProtectedPage) {
            const message = document.createElement("p");
            message.setAttribute("role", "alert");
            message.textContent = "Não foi possível verificar seu login. Atualize a página ou tente novamente.";
            document.querySelector(".menu_header")?.append(message);
        }
    });
