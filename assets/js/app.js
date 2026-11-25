document.addEventListener('DOMContentLoaded', function () {
  var menuToggle = document.querySelector('.menu-toggle');
  var mainNav = document.querySelector('.main-nav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  var adminMenuToggle = document.querySelector('.admin-menu-toggle');
  var sidebar = document.querySelector('.admin-sidebar');
  if (adminMenuToggle && sidebar) {
    adminMenuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('is-open');
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  var dateInput = document.querySelector('[data-booking-date]');
  var slots = document.querySelector('.time-slots');
  if (dateInput && slots) {
    var labels = {
      '09:00': '9:00 AM',
      '10:30': '10:30 AM',
      '12:00': '12:00 PM',
      '13:30': '1:30 PM',
      '15:00': '3:00 PM',
      '16:30': '4:30 PM'
    };
    var refreshSlots = function () {
      if (!dateInput.value) return;
      slots.setAttribute('aria-busy', 'true');
      fetch('available-slots.php?date=' + encodeURIComponent(dateInput.value), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      }).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) throw new Error(data.error || 'Could not load appointment times.');
          return data;
        });
      }).then(function (data) {
        slots.replaceChildren();
        if (data.closed) {
          var closed = document.createElement('p');
          closed.className = 'form-hint';
          closed.textContent = 'The salon is closed on this day. Please choose Tuesday to Saturday.';
          slots.appendChild(closed);
          return;
        }
        Object.keys(labels).forEach(function (time) {
          var taken = data.booked.indexOf(time) !== -1;
          var label = document.createElement('label');
          label.className = 'time-slot' + (taken ? ' is-booked' : '');
          var input = document.createElement('input');
          input.type = 'radio';
          input.name = 'appointment_time';
          input.value = time + ':00';
          input.required = true;
          input.disabled = taken;
          var text = document.createElement('span');
          text.textContent = labels[time] + (taken ? ' · Taken' : '');
          label.appendChild(input);
          label.appendChild(text);
          slots.appendChild(label);
        });
      }).catch(function (error) {
        slots.replaceChildren();
        var message = document.createElement('p');
        message.className = 'form-hint';
        message.textContent = error.message + ' Refresh the page to try again.';
        slots.appendChild(message);
      }).finally(function () {
        slots.removeAttribute('aria-busy');
      });
    };
    dateInput.addEventListener('change', refreshSlots);
    refreshSlots();
  }
});
