import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const alertClasses = {
  popup: 'talentora-alert',
  title: 'talentora-alert-title',
  htmlContainer: 'talentora-alert-copy',
  actions: 'talentora-alert-actions',
  confirmButton: 'talentora-alert-confirm',
  cancelButton: 'talentora-alert-cancel',
  timerProgressBar: 'talentora-alert-progress',
};

function resourceName(path) {
  if (path.includes('/job-positions')) return 'posisi';
  if (path.includes('/candidates') && path.endsWith('/cv')) return 'CV kandidat';
  if (path.includes('/candidates')) return 'kandidat';
  if (path.includes('/applications')) return 'lamaran';
  if (path.includes('/interviews')) return 'jadwal interview';
  if (path.includes('/users')) return 'akun pengguna';
  if (path.endsWith('/me/password')) return 'password';
  if (path.endsWith('/me')) return 'profil';
  if (path.includes('/app-settings')) return 'pengaturan aplikasi';
  return 'data';
}

export async function confirmMutation(method, path, overrides = {}) {
  const deleting = method === 'delete';
  const adding = method === 'post';
  const name = resourceName(path);
  const result = await Swal.fire({
    icon: overrides.danger || deleting ? 'warning' : 'question',
    title: overrides.title || (deleting ? `Hapus ${name}?` : adding ? `Tambahkan ${name}?` : `Simpan perubahan ${name}?`),
    text: overrides.text || (deleting
      ? 'Data ini akan dihapus dan tindakan ini tidak dapat dibatalkan.'
      : 'Perubahan akan disimpan ke sistem Talentora.'),
    showCancelButton: true,
    reverseButtons: true,
    focusCancel: true,
    allowOutsideClick: false,
    buttonsStyling: false,
    customClass: {
      ...alertClasses,
      confirmButton: overrides.danger || deleting
        ? 'talentora-alert-confirm talentora-alert-confirm-danger'
        : alertClasses.confirmButton,
    },
    confirmButtonText: overrides.confirmButtonText || (deleting ? 'Ya, hapus' : adding ? 'Ya, tambahkan' : 'Ya, simpan'),
    cancelButtonText: 'Batal',
    heightAuto: false,
  });

  return result.isConfirmed;
}

export function showSuccessAlert(message) {
  return Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: message,
    showConfirmButton: false,
    timer: 2800,
    timerProgressBar: true,
    buttonsStyling: false,
    customClass: alertClasses,
    heightAuto: false,
  });
}

export function showErrorAlert(message) {
  return Swal.fire({
    icon: 'error',
    title: 'Tindakan gagal',
    text: message,
    confirmButtonText: 'Tutup',
    buttonsStyling: false,
    customClass: alertClasses,
    heightAuto: false,
  });
}
