(function () {
  "use strict";

  function debounce(fn, wait) {
    var timer;
    return function () {
      var args = arguments;
      var ctx = this;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  function renderResults(container, products) {
    if (!products.length) {
      container.innerHTML = "";
      return;
    }

    var html = '<div class="fxr-zoeker__grid">';
    products.forEach(function (p) {
      html +=
        '<a class="fxr-zoeker__card" href="' +
        p.permalink +
        '">' +
        '<img class="fxr-zoeker__card-img" src="' +
        p.image +
        '" alt="' +
        escapeHtml(p.title) +
        '" loading="lazy" />' +
        '<div class="fxr-zoeker__card-body">' +
        '<h3 class="fxr-zoeker__card-title">' +
        escapeHtml(p.title) +
        "</h3>" +
        '<div class="fxr-zoeker__card-price">' +
        p.price_html +
        "</div>" +
        (p.in_stock
          ? ""
          : '<div class="fxr-zoeker__card-stock">Niet op voorraad</div>') +
        "</div>" +
        "</a>";
    });
    html += "</div>";
    container.innerHTML = html;
  }

  function setOptions(select, items, placeholder) {
    var html = '<option value="">' + escapeHtml(placeholder) + "</option>";
    items.forEach(function (item) {
      html +=
        '<option value="' +
        item.id +
        '">' +
        escapeHtml(item.name) +
        "</option>";
    });
    select.innerHTML = html;
  }

  function ajax(action, params) {
    var body = new URLSearchParams();
    body.append("action", action);
    body.append("nonce", fxrZoeker.nonce);
    Object.keys(params).forEach(function (key) {
      body.append(key, params[key]);
    });

    return fetch(fxrZoeker.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: body,
    }).then(function (res) {
      return res.json();
    });
  }

  /**
   * Mode "dropdown": Merk-select -> Serie-select -> modelnummer-invoerveld.
   * Ongewijzigd t.o.v. de eerdere versie van deze zoeker.
   */
  function initDropdownZoeker(wrapper) {
    var taxonomy = wrapper.getAttribute("data-taxonomy");
    var minChars = parseInt(wrapper.getAttribute("data-min-chars"), 10) || 1;

    var merkSelect = wrapper.querySelector(".fxr-zoeker__select--merk");
    var serieSelect = wrapper.querySelector(".fxr-zoeker__select--serie");
    var nummerInput = wrapper.querySelector(".fxr-zoeker__input--nummer");
    var loader = wrapper.querySelector(".fxr-zoeker__loader");
    var statusText = wrapper.querySelector(".fxr-zoeker__status-text");
    var results = wrapper.querySelector(".fxr-zoeker__results");

    function resetResults() {
      loader.hidden = true;
      statusText.textContent = "";
      results.innerHTML = "";
    }

    function resetSerieAndNummer() {
      serieSelect.disabled = true;
      setOptions(serieSelect, [], "Kies eerst een merk...");
      nummerInput.disabled = true;
      nummerInput.value = "";
      resetResults();
    }

    // Stap 1: Merk gekozen -> series ophalen.
    merkSelect.addEventListener("change", function () {
      var merkId = merkSelect.value;
      resetSerieAndNummer();

      if (!merkId) {
        return;
      }

      setOptions(serieSelect, [], "Laden...");

      ajax("fxr_get_series", { taxonomy: taxonomy, merk_id: merkId })
        .then(function (json) {
          if (!json.success || !json.data.series.length) {
            setOptions(serieSelect, [], "Geen series gevonden");
            return;
          }
          setOptions(serieSelect, json.data.series, "Kies een serie...");
          serieSelect.disabled = false;
        })
        .catch(function () {
          setOptions(serieSelect, [], "Er ging iets mis");
        });
    });

    // Stap 2: Serie gekozen -> nummerveld vrijgeven.
    serieSelect.addEventListener("change", function () {
      nummerInput.value = "";
      resetResults();
      nummerInput.disabled = !serieSelect.value;
      if (!nummerInput.disabled) {
        nummerInput.focus();
      }
    });

    // Stap 3: Nummer typen -> zoeken binnen gekozen merk + serie.
    var doSearch = debounce(function () {
      var serieId = serieSelect.value;
      var nummer = nummerInput.value.trim();

      if (!serieId || nummer.length < minChars) {
        resetResults();
        return;
      }

      loader.hidden = false;
      statusText.textContent = "";

      ajax("fxr_zoek_onderdelen", {
        taxonomy: taxonomy,
        serie_id: serieId,
        nummer: nummer,
      })
        .then(function (json) {
          loader.hidden = true;

          if (!json.success) {
            statusText.textContent = "Er ging iets mis, probeer het opnieuw.";
            results.innerHTML = "";
            return;
          }

          var data = json.data;
          statusText.textContent =
            data.message || data.products.length + " onderdelen gevonden";
          renderResults(results, data.products);
        })
        .catch(function () {
          loader.hidden = true;
          statusText.textContent = "Er ging iets mis, probeer het opnieuw.";
        });
    }, 350);

    nummerInput.addEventListener("input", doSearch);
  }

  /**
   * Mode "autocomplete": één zoekveld + voorstellenlijst (combobox-patroon).
   * Belangrijkste regel: resultaten verschijnen alleen nadat de bezoeker
   * een voorstel uit de lijst heeft gekozen (klik, Enter of Tab) — nooit
   * op basis van los getypte tekst. Zolang er geen geldige keuze is
   * vastgelegd, blijft de resultaten-sectie leeg.
   */
  function initAutocompleteZoeker(wrapper) {
    var taxonomy = wrapper.getAttribute("data-taxonomy");
    var minChars = parseInt(wrapper.getAttribute("data-min-chars"), 10) || 2;

    var input = wrapper.querySelector(".fxr-zoeker__input--model");
    var hiddenModelId = wrapper.querySelector(".fxr-zoeker__model-id");
    var listbox = wrapper.querySelector(".fxr-combobox__listbox");
    var loader = wrapper.querySelector(".fxr-zoeker__loader");
    var statusText = wrapper.querySelector(".fxr-zoeker__status-text");
    var results = wrapper.querySelector(".fxr-zoeker__results");

    var currentSuggestions = []; // Laatst opgehaalde voorstellen (array van model-objecten).
    var activeIndex = -1; // Welk voorstel heeft nu het toetsenbord-highlight.

    function resetResults() {
      loader.hidden = true;
      statusText.textContent = "";
      results.innerHTML = "";
    }

    function closeListbox() {
      listbox.hidden = true;
      listbox.innerHTML = "";
      currentSuggestions = [];
      activeIndex = -1;
      input.setAttribute("aria-expanded", "false");
      input.removeAttribute("aria-activedescendant");
    }

    function forgetSelection() {
      // Wordt aangeroepen zodra de klant weer gaat typen: een eerder
      // gekozen model telt dan niet meer mee, dus resultaten verdwijnen
      // totdat er opnieuw een voorstel wordt gekozen.
      hiddenModelId.value = "";
      resetResults();
    }

    function renderSuggestions(items) {
      currentSuggestions = items;
      activeIndex = -1;

      if (!items.length) {
        listbox.innerHTML =
          '<li class="fxr-combobox__empty" role="presentation">Niets gevonden</li>';
        listbox.hidden = false;
        input.setAttribute("aria-expanded", "true");
        return;
      }

      var html = "";
      items.forEach(function (item, index) {
        var breadcrumb = [item.merk, item.serie, item.name]
          .filter(Boolean)
          .join(" › ");
        html +=
          '<li role="option" id="fxr-optie-' +
          index +
          '" data-index="' +
          index +
          '" class="fxr-combobox__option">' +
          '<span class="fxr-combobox__name">' +
          escapeHtml(item.name) +
          "</span>" +
          (breadcrumb
            ? '<span class="fxr-combobox__breadcrumb">' +
              escapeHtml(breadcrumb) +
              "</span>"
            : "") +
          "</li>";
      });
      listbox.innerHTML = html;
      listbox.hidden = false;
      input.setAttribute("aria-expanded", "true");
    }

    function highlightOption(index) {
      var options = listbox.querySelectorAll(".fxr-combobox__option");
      options.forEach(function (el) {
        el.classList.remove("is-active");
      });
      if (index >= 0 && options[index]) {
        options[index].classList.add("is-active");
        options[index].scrollIntoView({ block: "nearest" });
        input.setAttribute("aria-activedescendant", options[index].id);
      } else {
        input.removeAttribute("aria-activedescendant");
      }
      activeIndex = index;
    }

    function selectSuggestion(index) {
      var item = currentSuggestions[index];
      if (!item) {
        return;
      }

      // Zichtbare veldwaarde: gewoon de modelnaam. Merk/serie zie je
      // terug in de statusregel zodra de resultaten binnen zijn.
      input.value = item.name;
      hiddenModelId.value = item.id;
      closeListbox();
      zoekProductenVoorModel(item);
    }

    function zoekProductenVoorModel(item) {
      loader.hidden = false;
      statusText.textContent = "";
      results.innerHTML = "";

      ajax("fxr_zoek_op_model", { taxonomy: taxonomy, model_id: item.id })
        .then(function (json) {
          loader.hidden = true;

          if (!json.success) {
            statusText.textContent = "Er ging iets mis, probeer het opnieuw.";
            return;
          }

          var breadcrumb = [item.merk, item.serie, item.name]
            .filter(Boolean)
            .join(" › ");
          var data = json.data;
          statusText.textContent =
            breadcrumb +
            " — " +
            (data.message || data.products.length + " onderdelen gevonden");
          renderResults(results, data.products);
        })
        .catch(function () {
          loader.hidden = true;
          statusText.textContent = "Er ging iets mis, probeer het opnieuw.";
        });
    }

    var zoekVoorstellen = debounce(function () {
      var q = input.value.trim();

      if (q.length < minChars) {
        closeListbox();
        return;
      }

      ajax("fxr_search_models", { taxonomy: taxonomy, q: q })
        .then(function (json) {
          if (!json.success) {
            closeListbox();
            return;
          }
          renderSuggestions(json.data.models);
        })
        .catch(function () {
          closeListbox();
        });
    }, 300);

    // Typen: eerdere keuze (indien die er was) laten vallen + nieuwe
    // voorstellen ophalen. Resultaten blijven leeg totdat er weer
    // bewust een voorstel wordt gekozen.
    input.addEventListener("input", function () {
      forgetSelection();
      zoekVoorstellen();
    });

    // Toetsenbord: pijltjes om door de lijst te lopen, Enter om te
    // kiezen, Escape om te sluiten — zelfde bediening als een native
    // <select>, maar dan voor onze eigen voorstellenlijst.
    input.addEventListener("keydown", function (e) {
      if (listbox.hidden || !currentSuggestions.length) {
        return;
      }

      if ("ArrowDown" === e.key) {
        e.preventDefault();
        highlightOption(
          Math.min(activeIndex + 1, currentSuggestions.length - 1),
        );
      } else if ("ArrowUp" === e.key) {
        e.preventDefault();
        highlightOption(Math.max(activeIndex - 1, 0));
      } else if ("Enter" === e.key) {
        if (activeIndex >= 0) {
          e.preventDefault();
          selectSuggestion(activeIndex);
        }
      } else if ("Escape" === e.key) {
        closeListbox();
      }
    });

    // Klikken op een voorstel. We gebruiken "mousedown" (niet "click") en
    // voorkomen het standaardgedrag, zodat het invoerveld zijn focus niet
    // al kwijtraakt (en de lijst niet al dichtklapt) vóórdat de keuze is
    // verwerkt.
    listbox.addEventListener("mousedown", function (e) {
      var option = e.target.closest(".fxr-combobox__option");
      if (!option) {
        return;
      }
      e.preventDefault();
      selectSuggestion(parseInt(option.getAttribute("data-index"), 10));
    });

    // Ergens anders klikken sluit de voorstellenlijst.
    document.addEventListener("click", function (e) {
      if (!wrapper.contains(e.target)) {
        closeListbox();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var wrappers = document.querySelectorAll(".fxr-zoeker");

    wrappers.forEach(function (wrapper) {
      if ("autocomplete" === wrapper.getAttribute("data-mode")) {
        initAutocompleteZoeker(wrapper);
      } else {
        initDropdownZoeker(wrapper);
      }
    });
  });
})();
