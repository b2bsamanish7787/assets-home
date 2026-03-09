<?php
/**
 * Buzznation Assets Management System
 * File: includes/footer.php
 * Description: HTML footer — scripts and AJAX notification polling
 * Author: Buzznation IT Team
 */
?>
    </div><!-- /.main-content -->
  </div><!-- /.wrapper -->

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- DataTables -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Custom JS -->
  <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
  <?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
