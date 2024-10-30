(function ($) {
  "use strict";
  window.jq = $;

  /** toastr configs */
  toastr.options = {
    closeButton: false,
    debug: false,
    newestOnTop: true,
    progressBar: true,
    positionClass: "toast-bottom-left",
    preventDuplicates: false,
    onclick: null,
    showDuration: "300",
    hideDuration: "600",
    timeOut: "3000",
    extendedTimeOut: "1000",
    showEasing: "swing",
    hideEasing: "linear",
    showMethod: "fadeIn",
    hideMethod: "fadeOut",
  };
})(jQuery);
jQuery(document).ready(function ($) {
  /** Add or update site in isee */
  $("#add_shop_btn").on("click", function (e) {
    e.preventDefault();
    const nonce = document.getElementById("settings_nonce").value;
    let buttonText = $(this).text();

    $.ajax({
      method: "POST",
      url: WPEI_ADMIN_AJAX.AJAX_URL,
      data: {
        security: WPEI_ADMIN_AJAX.SECURITY,
        action: "addSiteToIsee",
        nonce,
      },
      beforeSend: () => {
        $(this).attr("disabled", true).html(`
                    <i class="material-icons spin">sync</i>
                `);
      },
      success: (result, response, xhr) => {
        const msg = result.data.message;
        if (response != "success" || xhr.status != 200) {
          toastr.error(msg);
          return;
        }
        if (!Boolean(result.success)) {
          toastr.info(msg);
          return;
        }

        if (
          response == "success" &&
          xhr.status == 200 &&
          Boolean(result.success)
        ) {
          const container = document.querySelector(".alert");
          $(container)
            .html("")
            .fadeOut("fast")
            .append(
              `
                        <div class="alert-success">
                            <p>
                                <i class="material-icons">verified_user</i>
                                فروشگاه شما در موتور جستجوی آیسی ثبت شده است!
                            </p>
                        </div>
                    `
            )
            .fadeIn("slow")
            .end();
          toastr.success(msg);
          buttonText = WPEI_ADMIN_AJAX.SUBMIT_BTN_TEXT;
          setTimeout(() => {
            window.location.reload();
          }, 2000);
        }
      },
      complete: () => {
        $(this).attr("disabled", false).html(buttonText);
      },
      error: () => {},
      timeout: WPEI_ADMIN_AJAX.REQUEST_TIMEOUT,
    });
  });

  // search in products table
  const searchBtn = document.getElementById("search_submit");
  if (searchBtn) {
    searchBtn.addEventListener("click", (event) => {
      event.preventDefault();
      const targetElement = event.target || event.target.currentTarget;
      const action = targetElement.dataset.action;
      const params = new window.URLSearchParams(window.location.search);
      const groupingStatus = params.get("grouping");
      const searchInput = document.getElementById("search_input").value;

      let redirectUrl =
        searchInput !== "" ? `${action}&s=${searchInput}` : action;
      if (groupingStatus != "all") {
        redirectUrl =
          searchInput !== ""
            ? `${action}&s=${searchInput}&grouping=${groupingStatus}`
            : `${action}&grouping=${groupingStatus}`;
      }
      setTimeout(() => {
        window.location.assign(redirectUrl);
      }, 400);
    });
  }
});

/**
 * Get products on other sites with minimum prices by product name
 * @param {string} domain
 * @param {string} product_name
 * @param {string} product_price
 * @return {void}
 */
function getMinPrices(domain, product_name, product_price) {
  const nonce = document.getElementById("wpei_products_list_nonce").value;
  let container = document.getElementById("min_price_modal_content");
  let modalTable = document.getElementById("min_price_table");
  let modalBody = document.querySelector(".modal-body");
  const minPriceModalLoading = document.getElementById(
    "min_price_modal_loading"
  );
  const tableCaption = document.getElementById("min_price_table_caption");

  jq.ajax({
    method: "POST",
    data: {
      security: WPEI_ADMIN_AJAX.SECURITY,
      action: "getMinPrices",
      product_name,
      nonce,
    },
    url: WPEI_ADMIN_AJAX.AJAX_URL,
    beforeSend: () => {
      modalTable.style.display = "none";
      minPriceModalLoading.style.display = "flex";
    },
    complete: () => {
      modalTable.style.display = "table";
      minPriceModalLoading.style.display = "none";
    },
    success: (result, response, xhr) => {
      if (xhr.status !== 200) {
        jq(container).html(`<p>${WPEI_ADMIN_AJAX.UNEXPECTED_ERROR}</p>`);
        return;
      }

      const sites = result.data?.data;
      if (!sites) {
        jq(modalBody).append(
          `<tr><td colspan="3" style="text-align: center;padding: 12px;">${WPEI_ADMIN_AJAX.UNEXPECTED_ERROR}</td></tr>`
        );
        return;
      }

      const uniqueSites = _removeDuplicates(sites, ["title"]);
      // remove self site results
      const eligibleSites = uniqueSites.filter((site) => {
        const siteDomain = new URL(site.link).hostname;
        return siteDomain != domain;
      });

      jq(modalBody).html("").fadeOut("fast");
      if (eligibleSites?.length === 0) {
        jq(modalBody).append(
          `<tr><td colspan="3" style="text-align: center;padding: 12px;">${WPEI_ADMIN_AJAX.NO_DATA}</td></tr>`
        );
      } else {
        eligibleSites.forEach((site, i) => {
          const priceDelta = Math.abs(
            Number(product_price) - Number(site.price)
          );
          const isCheaper = Number(product_price) - Number(site.price) > 0;
          // calc product link
          const iseeBaseUrl = WPEI_ADMIN_AJAX.ISEE_BASE_URL;
          let refPid = "";
          let productName = "";
          for (const site of sites) {
            refPid = site.ref_pid;
            productName = site.name;
            if (Boolean(refPid)) break;
          }
          const siteIdBase64 = btoa(site.id);
          const productLink = Boolean(refPid)
            ? `${iseeBaseUrl}/products/${refPid}/${productName}/#shop${siteIdBase64}`
            : `#`;

          const hasPrice = site.price != 0;
          let priceStr = "";
          priceStr = `${_priceFormat(site.price.toString())} تومان`;
          if (!hasPrice) {
            priceStr = "بدون قیمت";
          }

          jq(modalBody).append(`
                    <tr>
                        <td>${++i}</td>
                        <td><a href="${productLink}" target="_blank">${
            site.title
          }</a></td>
                        <td>
                          ${priceStr}
                          <span class="${
                            isCheaper ? "cheaper" : "more-expensive"
                          }">( ${_priceFormat(priceDelta.toString())}${
            isCheaper ? "-" : "+"
          } )</span>
                        </td>
                    </tr>
                `);
        });
      }
      jq(modalBody).fadeIn("slow").end();
      // set table caption html
      jq(tableCaption)
        .find("strong")
        .html(
          `${product_name} (${
            product_price != 0
              ? _priceFormat(product_price.toString()) + "تومان"
              : "بدون قیمت"
          } )`
        );
    },
    error: (e) => {
      console.error(e.message);
    },
  });
}

const _priceFormat = (price_str) =>
  price_str.replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");

/**
 * Remove duplicate items from an array of objects per property_names
 * @param {Array} arr
 * @param {Array} property_names
 * @returns {Array}
 */
const _removeDuplicates = (arr, property_names) => {
  const arrCp = [...arr];
  return arrCp.filter(
    (value, index, array) =>
      array.findIndex((item) =>
        property_names.every((k) => item[k] === value[k])
      ) === index
  );
};
