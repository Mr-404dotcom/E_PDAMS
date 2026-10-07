const recordModal = document.querySelector('#record-modal');
const recordForm = document.querySelector('#record-form');
const recordFormTitle = document.querySelector('#record-form-title');
const recordFormError = document.querySelector('#record-form-error');
const recordId = document.querySelector('#record-id');

function setRecordForm(record = null) {
  recordForm.reset();
  recordFormError.textContent = '';
  recordFormTitle.textContent = record ? 'Edit record' : 'New record';
  recordId.value = record?.student_id || '';

  if (record) {
    for (const field of ['student_number', 'first_name', 'middle_name', 'last_name', 'gender', 'grade_level', 'section', 'status', 'parent_name', 'parent_contact', 'parent_email', 'address']) {
      const input = document.querySelector(`#${field.replaceAll('_', '-')}`);
      if (input) input.value = record[field] || '';
    }
  }

  recordModal.hidden = false;
  document.querySelector('#student-number').focus();
}

function closeRecordModal() {
  recordModal.hidden = true;
}

document.querySelector('#new-record-button')?.addEventListener('click', () => setRecordForm());
document.querySelector('#close-record-modal')?.addEventListener('click', closeRecordModal);
document.querySelector('#cancel-record')?.addEventListener('click', closeRecordModal);
recordModal?.addEventListener('click', (event) => {
  if (event.target === recordModal) closeRecordModal();
});

document.querySelectorAll('.edit-record').forEach((button) => {
  button.addEventListener('click', () => setRecordForm(JSON.parse(button.dataset.record)));
});

document.querySelectorAll('.delete-record').forEach((button) => {
  button.addEventListener('click', async () => {
    if (!window.confirm('Delete this student record?')) return;

    const response = await fetch(`../../server/api.php?resource=students&id=${button.dataset.id}`, { method: 'DELETE' });
    const result = await response.json();
    if (!response.ok || result.status !== 'success') {
      window.alert(result.message || 'Unable to delete the record.');
      return;
    }

    window.location.reload();
  });
});

recordForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  recordFormError.textContent = '';

  const payload = Object.fromEntries(new FormData(recordForm).entries());
  const editing = Boolean(recordId.value);
  const url = editing
    ? `../../server/api.php?resource=students&id=${recordId.value}`
    : '../../server/api.php?resource=students';

  const response = await fetch(url, {
    method: editing ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const result = await response.json();

  if (!response.ok || result.status !== 'success') {
    recordFormError.textContent = result.message || 'Unable to save the record.';
    return;
  }

  window.location.reload();
});

const violationModal = document.querySelector('#violation-modal');
const violationForm = document.querySelector('#violation-form');
const violationError = document.querySelector('#violation-form-error');
const violationStudentId = document.querySelector('#violation-student-id');
const violationStudentName = document.querySelector('#violation-student-name');
const violationDate = document.querySelector('#violation-date');

function openViolationModal(studentId, studentName) {
  violationForm.reset();
  violationError.textContent = '';
  if (studentId) violationStudentId.value = studentId;
  violationStudentName.textContent = studentName || violationStudentId.selectedOptions?.[0]?.dataset.name || '';
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  violationDate.value = now.toISOString().slice(0, 16);
  violationModal.hidden = false;
  document.querySelector('#violation-category').focus();
}

function closeViolationModal() {
  violationModal.hidden = true;
}

document.querySelectorAll('.add-violation').forEach((button) => {
  button.addEventListener('click', () => openViolationModal(button.dataset.studentId, button.dataset.studentName));
});
violationStudentId?.addEventListener('change', () => {
  violationStudentName.textContent = violationStudentId.selectedOptions?.[0]?.dataset.name || '';
});
document.querySelector('#close-violation-modal')?.addEventListener('click', closeViolationModal);
document.querySelector('#cancel-violation')?.addEventListener('click', closeViolationModal);
violationModal?.addEventListener('click', (event) => {
  if (event.target === violationModal) closeViolationModal();
});

violationForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  violationError.textContent = '';
  const payload = Object.fromEntries(new FormData(violationForm).entries());
  payload.date_time = payload.date_time.replace('T', ' ') + ':00';

  const response = await fetch('../../server/api.php?resource=violations', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const result = await response.json();

  if (!response.ok || result.status !== 'success') {
    violationError.textContent = result.message || 'Unable to add the violation.';
    return;
  }

  window.location.reload();
});
