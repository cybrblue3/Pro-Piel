// public/js/form.js
document.addEventListener("DOMContentLoaded", () => {
  const dateInput = document.getElementById("date");
  const birthInput = document.getElementById("birth_date");
  const specialtyInput = document.getElementById("specialty");
  const horariosDiv = document.getElementById("horarios");
  const hiddenTime = document.getElementById("appointment_time");
  const form = document.getElementById("patientForm");
  const terms = document.getElementById("terms");
  const nameInput = document.getElementById("full_name");
  const phoneInput = document.getElementById("phone");
  const phoneCcSelect = document.getElementById("phone_cc");
  const phoneFullHidden = document.getElementById("phone_full");
  const emailInput = document.getElementById("email");
  const sexSelect = document.getElementById("sex");
  const firstVisitContainer = document.getElementById("firstVisitContainer");

  const ALLOWED_DOMAINS = ["gmail.com","hotmail.com","outlook.com","icloud.com","yahoo.com"];

  // regex para permitir solo letras + espacios + acentos
  const nameFilterRegex = /[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]/g;
  const phoneFilterRegex = /[^0-9]/g;

  // inicializar flatpickr y máscara para solo números en visible altInput
  const initDatepick = (el) => {
    if(!el) return;
    const fp = flatpickr(el, {
      altInput: true,
      altFormat: "d-m-Y",
      dateFormat: "Y-m-d",
      allowInput: true,
      clickOpens: true,
      locale: 'es',
      onReady: function(selectedDates, dateStr, instance){
        attachNumericMaskToAltInput(instance);
      }
    });
    return fp;
  };

  function attachNumericMaskToAltInput(instance){
    // altInput es el input visible donde el usuario escribe
    const alt = instance.altInput;
    if(!alt) return;

    // evitar que peguen texto no numérico
    alt.addEventListener('paste', function(e){
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      const digits = text.replace(/\D/g, '').slice(0,8); // max 8 dígitos ddmmyyyy
      autoFormatAndSet(alt, digits, instance);
    });

    // input: solo aceptar dígitos; autoinsertar guiones (DD-MM-YYYY)
    alt.addEventListener('input', function(e){
      const cur = this.value;
      // eliminar todo excepto dígitos
      const digits = cur.replace(/\D/g, '').slice(0,8);
      autoFormatAndSet(alt, digits, instance);
    });

    // bloquear teclas no numéricas (permitir backspace, arrow keys, delete, tab)
    alt.addEventListener('keypress', function(e){
      const allowed = [8,9,13,37,38,39,40,46]; // backspace, tab, enter, arrows, delete
      if(allowed.includes(e.which || e.keyCode)) return;
      const ch = String.fromCharCode(e.which || e.keyCode);
      if(!/[0-9]/.test(ch)) e.preventDefault();
    });
  }

  function autoFormatAndSet(altInput, digits, instance){
    // digits: string solo dígitos (máx 8). Formatear como DD-MM-YYYY visualmente.
    let display = digits;
    if (digits.length >= 3) display = digits.slice(0,2) + '-' + digits.slice(2);
    if (digits.length >= 5) display = digits.slice(0,2) + '-' + digits.slice(2,4) + '-' + digits.slice(4);
    altInput.value = display;
    // Si tenemos 8 dígitos completos, actualizar el value real del flatpickr (YYYY-MM-DD)
    if (digits.length === 8) {
      const dd = digits.slice(0,2);
      const mm = digits.slice(2,4);
      const yyyy = digits.slice(4,8);
      // validar mes/día mínimos superficiales antes de setear
      const dNum = parseInt(dd,10), mNum = parseInt(mm,10), yNum = parseInt(yyyy,10);
      if (mNum >= 1 && mNum <= 12 && dNum >= 1 && dNum <= 31) {
        // setear en formato Y-m-d que envía flatpickr
        instance.setDate(`${yyyy}-${mm}-${dd}`, true, "Y-m-d");
      }
    } else {
      // si menos de 8 dígitos, limpiar el value real para evitar envíos parciales
      instance.clear();
    }
  }

  // inicializar ambos campos
  const fpDate = initDatepick(dateInput);
  const fpBirth = initDatepick(birthInput);

  // -- nombre: evitar signos/números en input y paste
  if (nameInput) {
    nameInput.addEventListener("input", function(){
      const cur = this.value;
      const cleaned = cur.replace(nameFilterRegex, '');
      if (cleaned !== cur) {
        const pos = this.selectionStart - (cur.length - cleaned.length);
        this.value = cleaned;
        this.setSelectionRange(pos,pos);
      }
      // validación visual
      if(this.value.trim().length >= 3) {
        this.classList.remove('is-invalid'); this.classList.add('is-valid');
      } else {
        this.classList.remove('is-valid'); this.classList.add('is-invalid');
      }
    });
    nameInput.addEventListener('keypress', function(e){
      const ch = String.fromCharCode(e.which || e.keyCode);
      if (nameFilterRegex.test(ch)) e.preventDefault();
    });
  }

  // -- teléfono: solo números + actualizar phone_full
  function updatePhoneFull() {
    const cc = (phoneCcSelect && phoneCcSelect.value) ? phoneCcSelect.value.replace('+','') : '';
    const phoneDigits = (phoneInput && phoneInput.value) ? phoneInput.value.replace(/\D/g,'') : '';
    if (cc && phoneDigits) {
      phoneFullHidden.value = cc + phoneDigits;
    } else {
      phoneFullHidden.value = '';
    }
  }

  if (phoneInput) {
    phoneInput.addEventListener("input", function(){
      const s = this.value.replace(phoneFilterRegex, '');
      if (s !== this.value) {
        const pos = this.selectionStart - (this.value.length - s.length);
        this.value = s;
        this.setSelectionRange(pos,pos);
      }
      if (this.value.length === 10) {
        this.classList.remove('is-invalid'); this.classList.add('is-valid');
      } else {
        this.classList.remove('is-valid'); this.classList.add('is-invalid');
      }
      updatePhoneFull();
    });
    phoneInput.addEventListener('keypress', function(e){
      const ch = String.fromCharCode(e.which || e.keyCode);
      if(/[^0-9]/.test(ch)) e.preventDefault();
    });
  }

  if (phoneCcSelect) {
    phoneCcSelect.addEventListener('change', function(){
      updatePhoneFull();
      // validación visual leve
      if (this.value) { this.classList.remove('is-invalid'); this.classList.add('is-valid'); }
      else { this.classList.remove('is-valid'); this.classList.add('is-invalid'); }
    });
    // inicializar hidden
    updatePhoneFull();
  }

  // sexo: exigir selección
  if(sexSelect){
    sexSelect.addEventListener('change', function(){
      if(this.value) { this.classList.remove('is-invalid'); this.classList.add('is-valid'); }
      else { this.classList.remove('is-valid'); this.classList.add('is-invalid'); }
    });
  }

  // Función para remover acentos/diacríticos (normaliza a ascii base)
  function removeDiacritics(str){
    if(!str) return '';
    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  // Mostrar / ocultar primera visita solo para dermatologia (por texto)
  function checkShowFirstVisit(){
    const rawText = specialtyInput.options[specialtyInput.selectedIndex]?.text || '';
    const selText = removeDiacritics(rawText.toLowerCase());
    // buscar 'dermatolog' (sin acento) cubre 'dermatologia' / 'dermatología'
    if(selText.includes('dermatolog')) {
      firstVisitContainer.style.display = '';
    } else {
      // limpiar selección si existe
      const radios = firstVisitContainer.querySelectorAll('input[type=checkbox], input[type=radio]');
      radios.forEach(r=> r.checked = false);
      // ocultar mensaje de error si existiera
      const errEl = document.getElementById('firstVisitError');
      if (errEl) errEl.style.display = 'none';
      firstVisitContainer.style.display = 'none';
    }
  }
  specialtyInput.addEventListener('change', () => {
    checkShowFirstVisit();
    // carga horarios (slots.js tiene su propia carga, se sincroniza por evento change)
    const ev = new Event('specialty:changed');
    document.dispatchEvent(ev);
  });
  checkShowFirstVisit();

  // Si el usuario marca alguna opción del radio, quitar error visual
  document.addEventListener('change', (e) => {
    if (!firstVisitContainer) return;
    if (e.target && e.target.name === 'is_first_time') {
      const errEl = document.getElementById('firstVisitError');
      if (errEl) errEl.style.display = 'none';
      firstVisitContainer.classList.remove('border','border-danger','p-2','rounded');
    }
  });

  // validaciones de fecha
  function calcularEdad(birth) {
    if (!birth) return null;
    const [y,m,d] = birth.split("-");
    const b = new Date(y, m-1, d);
    const today = new Date();
    let edad = today.getFullYear() - b.getFullYear();
    const mm = today.getMonth() - b.getMonth();
    if (mm < 0 || (mm === 0 && today.getDate() < b.getDate())) edad--;
    return edad;
  }

  function validarFechaCita(fecha) {
    if (!fecha) return false;
    const today = new Date();
    today.setHours(0,0,0,0);
    const partes = fecha.split("-");
    const d = new Date(parseInt(partes[0],10), parseInt(partes[1],10)-1, parseInt(partes[2],10));
    if (isNaN(d.getTime())) return false;
    if (d < today) return false;
    // evitar domingos (getDay: 0 domingo)
    if (d.getDay() === 0) return false;
    return true;
  }

  // Cambiamos la validación del submit para usar errores inline
  form.addEventListener("submit", (e) => {
    let ok = true;

    // nombre
    if (!nameInput.value || nameInput.value.trim().length < 3) {
      nameInput.classList.add('is-invalid'); ok = false;
    } else { nameInput.classList.remove('is-invalid'); nameInput.classList.add('is-valid'); }

    // telefono (aseguramos phone_full o phone local)
    updatePhoneFull();
    const phoneDigits = phoneInput.value ? phoneInput.value.replace(/\D/g,'') : '';
    if (!phoneDigits || !/^\d{10}$/.test(phoneDigits)) {
      phoneInput.classList.add('is-invalid'); ok = false;
    } else { phoneInput.classList.remove('is-invalid'); phoneInput.classList.add('is-valid'); }

    // sexo
    if (!sexSelect.value) { sexSelect.classList.add('is-invalid'); ok = false; }
    else { sexSelect.classList.remove('is-invalid'); sexSelect.classList.add('is-valid'); }

    // birth date
    const edad = calcularEdad(birthInput.value);
    if (edad === null || edad < 0 || edad > 95) {
      birthInput.classList.add('is-invalid'); ok = false;
    } else { birthInput.classList.remove('is-invalid'); birthInput.classList.add('is-valid'); }

    // appointment date
    if (!validarFechaCita(dateInput.value)) {
      dateInput.classList.add('is-invalid'); ok = false;
    } else { dateInput.classList.remove('is-invalid'); dateInput.classList.add('is-valid'); }

    // especialidad
    if (!specialtyInput.value) {
      specialtyInput.classList.add('is-invalid'); ok = false;
    } else { specialtyInput.classList.remove('is-invalid'); specialtyInput.classList.add('is-valid'); }

    // --- validar radio "¿Es tu primera visita?" si la especialidad es Dermatología
    const rawText = specialtyInput.options[specialtyInput.selectedIndex]?.text || '';
    const selText = removeDiacritics(rawText.toLowerCase());
    if (selText.includes('dermatolog')) {
      const fv = document.querySelector('input[name="is_first_time"]:checked');
      const errEl = document.getElementById('firstVisitError');
      if (!fv) {
        if (errEl) errEl.style.display = 'block';
        firstVisitContainer.classList.add('border','border-danger','p-2','rounded');
        ok = false;
      } else {
        if (errEl) errEl.style.display = 'none';
        firstVisitContainer.classList.remove('border','border-danger','p-2','rounded');
      }
    } else {
      // limpiar si no aplica
      const errEl = document.getElementById('firstVisitError');
      if (errEl) errEl.style.display = 'none';
      firstVisitContainer.classList.remove('border','border-danger','p-2','rounded');
    }

    // hora seleccionada
    if (!hiddenTime.value) {
      // destacar contenedor horarios
      horariosDiv.classList.add('border','border-danger','p-2','rounded');
      ok = false;
    } else {
      horariosDiv.classList.remove('border','border-danger','p-2','rounded');
    }

    // terms
    if (!terms.checked) {
      terms.classList.add('is-invalid'); ok = false;
    } else {
      terms.classList.remove('is-invalid'); terms.classList.add('is-valid');
    }

    // email domain (opcional)
    if (emailInput.value) {
      const parts = emailInput.value.split("@");
      if (parts.length !== 2 || !ALLOWED_DOMAINS.includes(parts[1].toLowerCase())) {
        emailInput.classList.add('is-invalid'); ok = false;
      } else {
        emailInput.classList.remove('is-invalid'); emailInput.classList.add('is-valid');
      }
    } else {
      emailInput.classList.remove('is-invalid'); emailInput.classList.remove('is-valid');
    }

    if (!ok) {
      e.preventDefault();
      e.stopPropagation();
      // scrollear al primer error
      const firstErr = document.querySelector('.is-invalid');
      if (firstErr) firstErr.scrollIntoView({behavior:'smooth', block:'center'});
    } else {
      // cuando todo ok, garantizamos que phone_full esté actualizado antes de enviar
      updatePhoneFull();
    }
  });

});
