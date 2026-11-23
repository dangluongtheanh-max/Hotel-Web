(function () {
  "use strict";

  var MAX_ROOMS = 6;
  var MIN_ROOMS = 1;
  var MIN_ADULTS = 1;
  var MAX_TOTAL_GUESTS = 20;

  function toState(input) {
    if (Array.isArray(input)) {
      var rooms = Math.max(MIN_ROOMS, Math.min(MAX_ROOMS, input.length || 1));
      var sumAdults = input.reduce(function (s, r) { return s + (Number(r && r.adults) || 0); }, 0);
      var sumChildren = input.reduce(function (s, r) { return s + (Number(r && r.children) || 0); }, 0);
      return normalizeState({
        adults: sumAdults || 2,
        children: sumChildren,
        rooms: rooms
      });
    }
    if (input && typeof input === "object") {
      return normalizeState(input);
    }
    return { adults: 2, children: 0, rooms: 1 };
  }

  function normalizeState(raw) {
    var rooms = parseInt(raw.rooms, 10);
    if (isNaN(rooms)) rooms = 1;
    rooms = Math.max(MIN_ROOMS, Math.min(MAX_ROOMS, rooms));

    var adults = parseInt(raw.adults, 10);
    if (isNaN(adults)) adults = 2;
    adults = Math.max(MIN_ADULTS, Math.min(MAX_TOTAL_GUESTS, adults));

    var children = parseInt(raw.children, 10);
    if (isNaN(children)) children = 0;
    children = Math.max(0, Math.min(MAX_TOTAL_GUESTS - adults, children));

    if (adults + children < rooms) {
      adults = Math.min(MAX_TOTAL_GUESTS - children, rooms - children);
    }
    return { adults: adults, children: children, rooms: rooms };
  }

  function totalGuests(value) {
    var st = toState(value);
    return st.adults + st.children;
  }

  function stepperRowHtml(kind, label, subLabel, value, minVal, maxVal) {
    return (
      '<div class="cp-room-customizer cp-occ-stepper-row" data-occ-row="' + kind + '">' +
        '<div class="cp-room-customizer-info">' +
          '<span>' + label + '</span>' +
          (subLabel ? '<small>' + subLabel + '</small>' : '') +
        '</div>' +
        '<div class="cp-room-stepper">' +
          '<button class="cp-room-step-btn" type="button" data-occ-step="-1" data-occ-target="' + kind + '" aria-label="Giảm ' + label + '"' +
          (value <= minVal ? ' disabled' : '') + '>−</button>' +
          '<input class="cp-room-count-input" type="number" min="' + minVal + '" max="' + maxVal + '" value="' + value + '" data-occ-input="' + kind + '" aria-label="' + label + '">' +
          '<button class="cp-room-step-btn" type="button" data-occ-step="1" data-occ-target="' + kind + '" aria-label="Tăng ' + label + '"' +
          (value >= maxVal ? ' disabled' : '') + '>+</button>' +
        '</div>' +
      '</div>'
    );
  }

  function init(root, initialValue, onApply) {
    var trigger = root.querySelector("[data-occupancy-trigger]");
    var panel = root.querySelector("[data-occupancy-panel]");
    var guestRowsBox = root.querySelector("[data-occupancy-rooms]");
    var decRoomBtn = root.querySelector("[data-occupancy-step='-1']");
    var incRoomBtn = root.querySelector("[data-occupancy-step='1']");
    var roomCountInput = root.querySelector("[data-occupancy-count]");
    var maxRoomsLabel = root.querySelector("[data-occupancy-max-rooms]");
    var applyButton = root.querySelector("[data-occupancy-apply]");
    var cancelButton = root.querySelector("[data-occupancy-cancel]");
    var error = root.querySelector("[data-occupancy-error]");

    var state = toState(initialValue);
    var draft = null;

    function updateSummary() {
      trigger.textContent =
        state.adults + " người lớn · " + state.children + " trẻ em · " + state.rooms + " phòng";
    }

    function renderDraft() {
      if (!draft) return;
      var totalNow = draft.adults + draft.children;
      var minAdultsAllowed = Math.max(MIN_ADULTS, draft.rooms - draft.children);
      var maxAdultsAllowed = MAX_TOTAL_GUESTS - draft.children;
      var minChildrenAllowed = Math.max(0, draft.rooms - draft.adults);
      var maxChildrenAllowed = MAX_TOTAL_GUESTS - draft.adults;

      if (guestRowsBox) {
        guestRowsBox.innerHTML =
          stepperRowHtml("adults", "Người lớn", "Trên 11 tuổi", draft.adults, minAdultsAllowed, maxAdultsAllowed) +
          stepperRowHtml("children", "Trẻ em", "≤ 11 tuổi", draft.children, minChildrenAllowed, maxChildrenAllowed);
      }

      if (roomCountInput) {
        roomCountInput.min = String(MIN_ROOMS);
        roomCountInput.max = String(MAX_ROOMS);
        roomCountInput.value = String(draft.rooms);
      }
      if (maxRoomsLabel) {
        maxRoomsLabel.textContent = "Tối đa " + MAX_ROOMS + " phòng";
      }
      if (decRoomBtn) {
        decRoomBtn.disabled = draft.rooms <= MIN_ROOMS;
      }
      if (incRoomBtn) {
        incRoomBtn.disabled = draft.rooms >= MAX_ROOMS || totalNow >= MAX_TOTAL_GUESTS;
      }
    }

    function applyChange(kind, rawValue) {
      if (!draft) return;
      var val = parseInt(rawValue, 10);
      if (isNaN(val)) {
        renderDraft();
        return;
      }

      var msg = "";
      if (kind === "rooms") {
        if (val < MIN_ROOMS) {
          val = MIN_ROOMS;
          msg = "Số phòng tối thiểu là " + MIN_ROOMS + " phòng.";
        } else if (val > MAX_ROOMS) {
          val = MAX_ROOMS;
          msg = "Số phòng tối đa có thể đặt là " + MAX_ROOMS + " phòng.";
        }
        draft.rooms = val;
        if (draft.adults + draft.children < draft.rooms) {
          var neededAdults = draft.rooms - draft.children;
          if (neededAdults + draft.children > MAX_TOTAL_GUESTS) {
            draft.rooms = draft.adults + draft.children;
            msg = "Tổng số khách tối đa là " + MAX_TOTAL_GUESTS + " người.";
          } else {
            draft.adults = Math.max(MIN_ADULTS, neededAdults);
          }
        }
      } else if (kind === "adults") {
        if (val < MIN_ADULTS) {
          val = MIN_ADULTS;
          msg = "Cần tối thiểu " + MIN_ADULTS + " người lớn.";
        } else if (val + draft.children > MAX_TOTAL_GUESTS) {
          val = MAX_TOTAL_GUESTS - draft.children;
          msg = "Tổng số khách không được vượt quá " + MAX_TOTAL_GUESTS + " người.";
        } else if (val + draft.children < draft.rooms) {
          val = Math.max(MIN_ADULTS, draft.rooms - draft.children);
          msg = "Mỗi phòng cần tối thiểu 1 khách (" + draft.rooms + " phòng cần tối thiểu " + draft.rooms + " khách).";
        }
        draft.adults = val;
      } else if (kind === "children") {
        if (val < 0) {
          val = 0;
        } else if (draft.adults + val > MAX_TOTAL_GUESTS) {
          val = MAX_TOTAL_GUESTS - draft.adults;
          msg = "Tổng số khách không được vượt quá " + MAX_TOTAL_GUESTS + " người.";
        } else if (draft.adults + val < draft.rooms) {
          val = Math.max(0, draft.rooms - draft.adults);
          msg = "Mỗi phòng cần tối thiểu 1 khách (" + draft.rooms + " phòng cần tối thiểu " + draft.rooms + " khách).";
        }
        draft.children = val;
      }

      if (error) error.textContent = msg;
      renderDraft();
      positionPanel();
    }

    function positionPanel() {
      panel.classList.remove("is-above");
      panel.style.maxHeight = "";
      var panelRect = panel.getBoundingClientRect();
      var triggerRect = trigger.getBoundingClientRect();
      var spaceAbove = triggerRect.top - 12;
      var spaceBelow = window.innerHeight - triggerRect.bottom - 12;
      var openAbove = panelRect.bottom > window.innerHeight - 12 && spaceAbove > spaceBelow;
      if (openAbove) {
        panel.classList.add("is-above");
        panelRect = panel.getBoundingClientRect();
      }
      var availableSpace = openAbove ? spaceAbove : spaceBelow;
      if (panelRect.height > availableSpace) {
        panel.style.maxHeight = Math.max(0, availableSpace) + "px";
      }
    }

    function open() {
      draft = toState(state);
      if (error) error.textContent = "";
      renderDraft();
      panel.hidden = false;
      trigger.setAttribute("aria-expanded", "true");
      positionPanel();
    }

    function close() {
      panel.hidden = true;
      panel.classList.remove("is-above");
      panel.style.maxHeight = "";
      trigger.setAttribute("aria-expanded", "false");
      draft = null;
    }

    trigger.addEventListener("click", function (event) {
      event.stopPropagation();
      if (panel.hidden) open();
      else close();
    });
    if (cancelButton) cancelButton.addEventListener("click", close);
    if (applyButton) {
      applyButton.addEventListener("click", function () {
        if (!draft) return;
        if (draft.rooms < MIN_ROOMS || draft.rooms > MAX_ROOMS) {
          if (error) error.textContent = "Số phòng hợp lệ từ 1 đến " + MAX_ROOMS + " phòng.";
          return;
        }
        if (draft.adults < MIN_ADULTS) {
          if (error) error.textContent = "Cần tối thiểu 1 người lớn.";
          return;
        }
        if (draft.adults + draft.children < draft.rooms) {
          if (error) error.textContent = "Mỗi phòng cần tối thiểu 1 khách.";
          return;
        }
        if (draft.adults + draft.children > MAX_TOTAL_GUESTS) {
          if (error) error.textContent = "Tổng số khách không được vượt quá " + MAX_TOTAL_GUESTS + " người.";
          return;
        }
        state = toState(draft);
        updateSummary();
        close();
        if (onApply) onApply(toState(state));
      });
    }

    if (decRoomBtn) {
      decRoomBtn.addEventListener("click", function () {
        if (draft) applyChange("rooms", draft.rooms - 1);
      });
    }
    if (incRoomBtn) {
      incRoomBtn.addEventListener("click", function () {
        if (draft) applyChange("rooms", draft.rooms + 1);
      });
    }
    if (roomCountInput) {
      roomCountInput.addEventListener("change", function () {
        if (draft) applyChange("rooms", roomCountInput.value);
      });
    }

    panel.addEventListener("click", function (event) {
      var stepBtn = event.target.closest("[data-occ-step]");
      if (!stepBtn || !draft) return;
      event.stopPropagation();
      var kind = stepBtn.getAttribute("data-occ-target");
      var delta = parseInt(stepBtn.getAttribute("data-occ-step"), 10) || 0;
      if (kind && draft[kind] !== undefined) {
        applyChange(kind, draft[kind] + delta);
      }
    });

    panel.addEventListener("change", function (event) {
      var input = event.target.closest("[data-occ-input]");
      if (!input || !draft) return;
      var kind = input.getAttribute("data-occ-input");
      if (kind) applyChange(kind, input.value);
    });

    document.addEventListener("click", function (event) {
      var path = event.composedPath ? event.composedPath() : [];
      if (!panel.hidden && path.indexOf(root) === -1 && !root.contains(event.target)) close();
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !panel.hidden) {
        close();
        trigger.focus();
      }
    });
    window.addEventListener("resize", function () {
      if (!panel.hidden) positionPanel();
    });

    updateSummary();
    return {
      getValue: function () { return toState(state); },
      setValue: function (nextVal) {
        state = toState(nextVal);
        updateSummary();
      }
    };
  }

  window.SkyOccupancyPicker = {
    init: init,
    totalGuests: totalGuests,
    toState: toState
  };
})();
