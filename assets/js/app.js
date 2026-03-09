/**
 * Buzznation Assets Management System
 * File: assets/js/app.js
 * Description: Client-side JS — CSRF, DataTables, SweetAlert2, notifications
 * Author: Buzznation IT Team
 */

$(function () {
  'use strict';

  // ------------------------------------------------------------------
  // CSRF: inject into every AJAX request
  // ------------------------------------------------------------------
  const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
  $.ajaxSetup({
    headers: { 'X-CSRF-Token': csrfToken }
  });

  // ------------------------------------------------------------------
  // Sidebar toggle (mobile)
  // ------------------------------------------------------------------
  const sidebar  = $('#sidebar');
  const overlay  = $('<div class="sidebar-overlay"></div>').appendTo('body');

  $('#sidebarToggle').on('click', function () {
    sidebar.toggleClass('open');
    overlay.toggleClass('active');
  });

  overlay.on('click', function () {
    sidebar.removeClass('open');
    overlay.removeClass('active');
  });

  // ------------------------------------------------------------------
  // DataTables — initialise all tables with .datatable class
  // ------------------------------------------------------------------
  if ($.fn.DataTable) {
    $('.datatable').each(function () {
      if (!$.fn.DataTable.isDataTable(this)) {
        $(this).DataTable({
          responsive: true,
          pageLength: 25,
          order: [[0, 'desc']],
          language: {
            search: '<i class="bi bi-search"></i> _INPUT_',
            searchPlaceholder: 'Search…',
            emptyTable: 'No records found.',
            zeroRecords: 'No matching records found.'
          },
          dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-5'i><'col-sm-7'p>>"
        });
      }
    });
  }

  // ------------------------------------------------------------------
  // SweetAlert2 — confirm dialogs for delete / approve / reject
  // ------------------------------------------------------------------

  // Generic delete confirmation
  $(document).on('click', '.btn-delete', function (e) {
    e.preventDefault();
    const href   = $(this).attr('href') || $(this).data('href');
    const target = $(this).data('name') || 'this item';
    Swal.fire({
      title: 'Delete ' + target + '?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it!'
    }).then(function (result) {
      if (result.isConfirmed && href) window.location.href = href;
    });
  });

  // Approve action
  $(document).on('click', '.btn-approve', function (e) {
    e.preventDefault();
    const href = $(this).attr('href') || $(this).data('href');
    Swal.fire({
      title: 'Approve this request?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#198754',
      confirmButtonText: 'Yes, approve!'
    }).then(function (result) {
      if (result.isConfirmed && href) window.location.href = href;
    });
  });

  // Reject action (prompts for remarks)
  $(document).on('click', '.btn-reject', function (e) {
    e.preventDefault();
    const href = $(this).attr('href') || $(this).data('href');
    Swal.fire({
      title: 'Reject this request?',
      input: 'textarea',
      inputLabel: 'Remarks (optional)',
      inputPlaceholder: 'Enter reason for rejection…',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'Reject'
    }).then(function (result) {
      if (result.isConfirmed && href) {
        const remarks = encodeURIComponent(result.value || '');
        window.location.href = href + (href.includes('?') ? '&' : '?') + 'remarks=' + remarks;
      }
    });
  });

  // ------------------------------------------------------------------
  // Flash message auto-dismiss
  // ------------------------------------------------------------------
  setTimeout(function () {
    $('.alert-dismissible.auto-dismiss').fadeOut('slow');
  }, 4000);

  // ------------------------------------------------------------------
  // File upload preview
  // ------------------------------------------------------------------
  $(document).on('change', '.file-upload-input', function () {
    const file    = this.files[0];
    const preview = $(this).siblings('.upload-preview');
    if (!file) { preview.hide(); return; }

    const type = file.type;
    if (type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = function (e) {
        preview.attr('src', e.target.result).show();
      };
      reader.readAsDataURL(file);
    } else {
      preview.hide();
    }
  });

  // ------------------------------------------------------------------
  // Toggle "purchased by" bill upload field on asset submit
  // ------------------------------------------------------------------
  $('input[name="purchased_by"]').on('change', function () {
    if ($(this).val() === 'me') {
      $('#bill-upload-section').slideDown();
      $('#bill_file').prop('required', true);
    } else {
      $('#bill-upload-section').slideUp();
      $('#bill_file').prop('required', false).val('');
      $('.upload-preview').hide();
    }
  });

  // ------------------------------------------------------------------
  // AJAX: Poll notifications every 30 seconds
  // ------------------------------------------------------------------
  function fetchNotifications () {
    $.get(siteUrl + '/ajax/notifications.php', { action: 'count' }, function (data) {
      if (data && data.count !== undefined) {
        const badge = $('#notif-count');
        if (data.count > 0) {
          badge.text(data.count).removeClass('d-none');
        } else {
          badge.addClass('d-none');
        }
      }
    }, 'json').fail(function () { /* silent fail */ });
  }

  // Only poll if user is logged in (siteUrl is defined in page)
  if (typeof siteUrl !== 'undefined') {
    fetchNotifications();
    setInterval(fetchNotifications, 30000);
  }

  // ------------------------------------------------------------------
  // Bootstrap form validation
  // ------------------------------------------------------------------
  $('form.needs-validation').on('submit', function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass('was-validated');
  });
});
