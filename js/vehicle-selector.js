/**
 * WheelPros Vehicle Selector - Cascading Dropdown Logic
 *
 * Handles year/make/model/trim selection with AJAX calls
 * to WheelPros Vehicle API
 */

(function ($) {
	"use strict";

	const VehicleSelector = {
		/**
		 * Current selections
		 */
		selections: {
			year: null,
			make: null,
			model: null,
			submodel: null,
			type: null,
		},

		/**
		 * Initialize
		 */
		init: function () {
			this.$container = $(".hp-vehicle-selector");

			if (!this.$container.length) {
				return;
			}

			this.$yearSelect = $("#hp-vehicle-year");
			this.$makeSelect = $("#hp-vehicle-make");
			this.$modelSelect = $("#hp-vehicle-model");
			this.$submodelSelect = $("#hp-vehicle-submodel");
			this.$submitBtn = $(".hp-vehicle-selector__submit");
			this.$resetBtn = $(".hp-vehicle-selector__reset");
			this.$loading = $(".hp-vehicle-selector__loading");
			this.$specs = $(".hp-vehicle-specs");

			this.selections.type = this.$container.data("type") || "";
			this.callback = this.$container.data("callback") || null;

			this.bindEvents();
			this.loadYears();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			this.$yearSelect.on("change", this.onYearChange.bind(this));
			this.$makeSelect.on("change", this.onMakeChange.bind(this));
			this.$modelSelect.on("change", this.onModelChange.bind(this));
			this.$submodelSelect.on("change", this.onSubmodelChange.bind(this));
			this.$submitBtn.on("click", this.onSubmit.bind(this));
			this.$resetBtn.on("click", this.onReset.bind(this));
		},

		/**
		 * Show loading state
		 */
		showLoading: function ($select) {
			$select.prop("disabled", true);
			$select.html(
				'<option value="">' + hpVehicleSelector.strings.loading + "</option>"
			);
			this.$loading.show();
		},

		/**
		 * Hide loading state
		 */
		hideLoading: function () {
			this.$loading.hide();
		},

		/**
		 * Populate dropdown with options
		 */
		populateDropdown: function ($select, items, placeholder) {
			$select.html('<option value="">' + placeholder + "</option>");

			if (Array.isArray(items)) {
				items.forEach(function (item) {
					const value = typeof item === "object" ? item.name || item : item;
					$select.append(
						'<option value="' + value + '">' + value + "</option>"
					);
				});
			}

			$select.prop("disabled", false);
		},

		/**
		 * Reset dropdown
		 */
		resetDropdown: function ($select, placeholder) {
			$select.html('<option value="">' + placeholder + "</option>");
			$select.prop("disabled", true);
			$select.val("");
		},

		/**
		 * Load years
		 */
		loadYears: function () {
			const self = this;

			this.showLoading(this.$yearSelect);

			$.ajax({
				url: hpVehicleSelector.ajaxurl,
				method: "POST",
				data: {
					action: "hp_get_vehicle_years",
					nonce: hpVehicleSelector.nonce,
					type: this.selections.type,
				},
				success: function (response) {
					self.hideLoading();

					if (response.success && response.data) {
						self.populateDropdown(
							self.$yearSelect,
							response.data,
							hpVehicleSelector.strings.selectYear
						);
					} else {
						var errorMsg = response.data || hpVehicleSelector.strings.error;
						console.error("Vehicle API Error:", errorMsg);
						self.$yearSelect.html(
							'<option value="">Error: ' + errorMsg + "</option>"
						);
					}
				},
				error: function (xhr, status, error) {
					self.hideLoading();
					console.error("AJAX Error:", status, error);
					self.$yearSelect.html(
						'<option value="">API Error - Check console</option>'
					);
				},
			});
		},

		/**
		 * Year changed
		 */
		onYearChange: function () {
			this.selections.year = this.$yearSelect.val();

			// Reset dependent dropdowns
			this.resetDropdown(
				this.$makeSelect,
				hpVehicleSelector.strings.selectMake
			);
			this.resetDropdown(
				this.$modelSelect,
				hpVehicleSelector.strings.selectModel
			);
			this.resetDropdown(
				this.$submodelSelect,
				hpVehicleSelector.strings.selectTrim
			);
			this.selections.make = null;
			this.selections.model = null;
			this.selections.submodel = null;
			this.$submitBtn.prop("disabled", true);
			this.$resetBtn.hide();
			this.$specs.hide();

			if (!this.selections.year) {
				return;
			}

			this.loadMakes();
		},

		/**
		 * Load makes for selected year
		 */
		loadMakes: function () {
			const self = this;

			this.showLoading(this.$makeSelect);

			$.ajax({
				url: hpVehicleSelector.ajaxurl,
				method: "POST",
				data: {
					action: "hp_get_vehicle_makes",
					nonce: hpVehicleSelector.nonce,
					year: this.selections.year,
					type: this.selections.type,
				},
				success: function (response) {
					self.hideLoading();

					if (response.success && response.data) {
						self.populateDropdown(
							self.$makeSelect,
							response.data,
							hpVehicleSelector.strings.selectMake
						);
					} else {
						alert(hpVehicleSelector.strings.error);
					}
				},
				error: function () {
					self.hideLoading();
					alert(hpVehicleSelector.strings.error);
				},
			});
		},

		/**
		 * Make changed
		 */
		onMakeChange: function () {
			this.selections.make = this.$makeSelect.val();

			// Reset dependent dropdowns
			this.resetDropdown(
				this.$modelSelect,
				hpVehicleSelector.strings.selectModel
			);
			this.resetDropdown(
				this.$submodelSelect,
				hpVehicleSelector.strings.selectTrim
			);
			this.selections.model = null;
			this.selections.submodel = null;
			this.$submitBtn.prop("disabled", true);
			this.$specs.hide();

			if (!this.selections.make) {
				return;
			}

			this.loadModels();
		},

		/**
		 * Load models for selected year/make
		 */
		loadModels: function () {
			const self = this;

			this.showLoading(this.$modelSelect);

			$.ajax({
				url: hpVehicleSelector.ajaxurl,
				method: "POST",
				data: {
					action: "hp_get_vehicle_models",
					nonce: hpVehicleSelector.nonce,
					year: this.selections.year,
					make: this.selections.make,
					type: this.selections.type,
				},
				success: function (response) {
					self.hideLoading();

					if (response.success && response.data) {
						self.populateDropdown(
							self.$modelSelect,
							response.data,
							hpVehicleSelector.strings.selectModel
						);
					} else {
						alert(hpVehicleSelector.strings.error);
					}
				},
				error: function () {
					self.hideLoading();
					alert(hpVehicleSelector.strings.error);
				},
			});
		},

		/**
		 * Model changed
		 */
		onModelChange: function () {
			this.selections.model = this.$modelSelect.val();

			// Reset dependent dropdown
			this.resetDropdown(
				this.$submodelSelect,
				hpVehicleSelector.strings.selectTrim
			);
			this.selections.submodel = null;
			this.$specs.hide();

			if (!this.selections.model) {
				this.$submitBtn.prop("disabled", true);
				return;
			}

			// Model is minimum required - enable submit
			this.$submitBtn.prop("disabled", false);
			this.$resetBtn.show();

			this.loadSubmodels();
		},

		/**
		 * Load submodels (trims) for selected year/make/model
		 */
		loadSubmodels: function () {
			const self = this;

			this.showLoading(this.$submodelSelect);

			$.ajax({
				url: hpVehicleSelector.ajaxurl,
				method: "POST",
				data: {
					action: "hp_get_vehicle_submodels",
					nonce: hpVehicleSelector.nonce,
					year: this.selections.year,
					make: this.selections.make,
					model: this.selections.model,
					type: this.selections.type,
				},
				success: function (response) {
					self.hideLoading();

					if (response.success && response.data) {
						self.populateDropdown(
							self.$submodelSelect,
							response.data,
							hpVehicleSelector.strings.selectTrim
						);
					}
				},
				error: function () {
					self.hideLoading();
				},
			});
		},

		/**
		 * Submodel changed
		 */
		onSubmodelChange: function () {
			this.selections.submodel = this.$submodelSelect.val();
			this.$specs.hide();
		},

		/**
		 * Load vehicle specifications
		 */
		loadVehicleSpecs: function () {
			const self = this;

			if (!this.$specs.length) {
				return;
			}

			this.$loading.show();

			$.ajax({
				url: hpVehicleSelector.ajaxurl,
				method: "POST",
				data: {
					action: "hp_get_vehicle_specs",
					nonce: hpVehicleSelector.nonce,
					year: this.selections.year,
					make: this.selections.make,
					model: this.selections.model,
					submodel: this.selections.submodel,
				},
				success: function (response) {
					self.$loading.hide();

					if (response.success && response.data) {
						self.displayVehicleSpecs(response.data);
					}
				},
				error: function () {
					self.$loading.hide();
				},
			});
		},

		/**
		 * Display vehicle specifications
		 */
		displayVehicleSpecs: function (specs) {
			const $content = this.$specs.find(".hp-vehicle-specs__content");
			let html = "<dl>";

			if (specs.axles && specs.axles.front) {
				const front = specs.axles.front;
				html +=
					"<dt>Bolt Pattern:</dt><dd>" +
					(front.boltPatternMm || "N/A") +
					"</dd>";
				html +=
					"<dt>Center Bore:</dt><dd>" +
					(front.centerBoreMm || "N/A") +
					" mm</dd>";
				html +=
					"<dt>OE Wheel Size:</dt><dd>" +
					(front.oeWidthIn || "N/A") +
					" inches</dd>";
				html +=
					"<dt>OE Tire Size:</dt><dd>" + (front.oeTireTx || "N/A") + "</dd>";

				if (front.offset) {
					html +=
						"<dt>Offset Range:</dt><dd>" +
						(front.offset.offsetMinMm || "N/A") +
						" to " +
						(front.offset.offsetMaxMm || "N/A") +
						" mm</dd>";
				}
			}

			html += "</dl>";
			$content.html(html);
			this.$specs.show();
		},

		/**
		 * Submit handler
		 */
		onSubmit: function (e) {
			e.preventDefault();

			// Load specs if enabled
			if (this.$specs.length) {
				this.loadVehicleSpecs();
			}

			// Call custom callback if provided
			if (this.callback && typeof window[this.callback] === "function") {
				window[this.callback](this.selections);
				return;
			}

			// Default behavior: trigger custom event
			$(document).trigger("hp_vehicle_selected", [this.selections]);

			// Save to session storage
			sessionStorage.setItem(
				"hp_selected_vehicle",
				JSON.stringify(this.selections)
			);

			console.log("Vehicle selected:", this.selections);
		},

		/**
		 * Reset form
		 */
		onReset: function (e) {
			e.preventDefault();

			this.selections = {
				year: null,
				make: null,
				model: null,
				submodel: null,
				type: this.selections.type,
			};

			this.$yearSelect.val("");
			this.resetDropdown(
				this.$makeSelect,
				hpVehicleSelector.strings.selectMake
			);
			this.resetDropdown(
				this.$modelSelect,
				hpVehicleSelector.strings.selectModel
			);
			this.resetDropdown(
				this.$submodelSelect,
				hpVehicleSelector.strings.selectTrim
			);
			this.$submitBtn.prop("disabled", true);
			this.$resetBtn.hide();
			this.$specs.hide();

			// Clear session storage
			sessionStorage.removeItem("hp_selected_vehicle");

			// Trigger reset event
			$(document).trigger("hp_vehicle_reset");
		},
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		VehicleSelector.init();
	});

	/**
	 * Expose to global scope for external use
	 */
	window.HPVehicleSelector = VehicleSelector;
})(jQuery);
