/**
 * Initializes the AI Sports Writer plugin functionality when the DOM is ready.
 * This function sets up event listeners for API testing, region fetching and saving,
 * and image uploading.
 *
 * @param {function($)} $ - The jQuery function
 * @returns {void}
 */
jQuery(document).ready(function ($) {
  $("#test-sport-api").on("click", function (e) {
    e.preventDefault();

    const button = $(this);
    button.prop("disabled", true).text("Testing...");

    $.post(
      fcg_ajax_object.ajax_url,
      {
        action: "test_sport_api",
        _ajax_nonce: fcg_ajax_object.nonce,
      },
      function (response) {
        if (response.success) {
          button.text("OK");
          fetchRegions();
        } else {
          button.text("Test API").prop("disabled", false);
          alert(response.data.message || "An error occurred");
        }
      }
    );
  });

  const fetchRegions = () => {
    $.ajax({
      url: fcg_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "fetch_regions",
        nonce: fcg_ajax_object.nonce,
      },
      success: function (response) {
        if (response.success) {
          const regions = response.data;
          const $select = $("#region-selection");
          $select.empty(); // Clear existing options

          regions.forEach((region) => {
            let isSelected = "";
            if (region.selected == 1) {
              console.log(region.selected);
              isSelected = "selected";
            }
            $select.append(
              `<option value="${region.id}" ${isSelected}>${region.name}</option>`
            );
          });
        } else {
          alert("Failed to fetch regions: " + response.data.message);
        }
      },
      error: function () {
        alert("An error occurred while fetching regions.");
      },
    });
  };

  const saveContentRegions = () => {
    const selectedRegions = $("#region-selection").val();
    $.ajax({
      url: fcg_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "save_content_regions",
        nonce: fcg_ajax_object.nonce,
        selected_regions: selectedRegions,
      },
      success: function (response) {
        if (response.success) {
          alert("Regions saved successfully!");
        } else {
          alert("Failed to save regions: " + response.data.message);
        }
      },
      error: function () {
        alert("An error occurred while saving regions.");
      },
    });
  };

  $("#save-regions").click(function (e) {
    e.preventDefault();
    saveContentRegions();
  });

  // Image Upload
  $("#upload_featured_image").on("click", function (e) {
    e.preventDefault();

    var image_frame;
    if (image_frame) {
      image_frame.open();
    }

    image_frame = wp.media({
      title: "Select Featured Image",
      multiple: false,
      library: {
        type: "image",
      },
      button: {
        text: "Use Image",
      },
    });

    image_frame.on("select", function () {
      var attachment = image_frame.state().get("selection").first().toJSON();
      $("#featured_image_url").val(attachment.url);
    });

    image_frame.open();
  });

  fetchRegions();
});
