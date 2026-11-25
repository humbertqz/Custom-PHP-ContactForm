const CONTACT_HEADERS = [
  "Submission ID",
  "Fecha",
  "Formulario",
  "Nombre",
  "Correo",
  "Teléfono",
  "Asunto",
  "Mensaje",
  "Campos adicionales",
  "URL de origen",
  "Estado",
  "Observaciones",
];
const CONTACT_FIELDS = ["name", "email", "phone", "subject", "message"];
const CONTACT_UTM_KEYS = [
  "utm_source",
  "utm_medium",
  "utm_campaign",
  "utm_id",
  "utm_term",
  "utm_content",
  "utm_source_platform",
  "utm_creative_format",
  "utm_marketing_tactic",
];

function doPost(event) {
  let lock;
  try {
    const config = PropertiesService.getScriptProperties();
    const token = config.getProperty("CONTACT_TOKEN");
    const spreadsheetId = config.getProperty("CONTACT_SPREADSHEET_ID");
    if (
      !token ||
      !/^[A-Za-z0-9_-]{32,256}$/.test(token) ||
      !spreadsheetId ||
      !/^[A-Za-z0-9_-]+$/.test(spreadsheetId)
    ) {
      return contactConfigurationError(
        "CONTACT_TOKEN o CONTACT_SPREADSHEET_ID ausente o inválido.",
      );
    }
    const post = event && event.postData;
    if (
      !post ||
      !/^application\/json(?:;|$)/i.test(post.type) ||
      !Number.isInteger(post.length) ||
      post.length > 131072 ||
      typeof post.contents !== "string" ||
      post.contents.length > 131072
    ) {
      return contactError("INVALID_REQUEST", false);
    }
    let body;
    try {
      body = JSON.parse(post.contents);
    } catch (_) {
      return contactError("INVALID_REQUEST", false);
    }
    if (
      !contactObject(body) ||
      Object.keys(body).some((key) => !["token", "submission"].includes(key))
    ) {
      return contactError("INVALID_REQUEST", false);
    }
    if (!contactToken(token, body.token))
      return contactError("UNAUTHORIZED", false);
    const record = body.submission;
    if (!contactRecord(record)) return contactError("INVALID_REQUEST", false);

    let routes;
    try {
      routes = JSON.parse(config.getProperty("CONTACT_FORM_MAP") || "{}");
    } catch (_) {
      return contactConfigurationError(
        "CONTACT_FORM_MAP contiene JSON inválido.",
      );
    }
    if (!contactObject(routes) || !contactOwn(routes, record.form_id)) {
      return contactError("INVALID_REQUEST", false);
    }
    const rule = routes[record.form_id];
    if (
      !contactObject(rule) ||
      typeof rule.tab !== "string" ||
      !Array.isArray(rule.fields) ||
      rule.fields.some((key) => !contactFieldName(key))
    ) {
      return contactConfigurationError(
        "La entrada del formulario debe incluir tab y fields válidos.",
      );
    }
    if (
      (record.route || record.form_id) !== (rule.route || record.form_id) ||
      Object.entries(record.fields).some(
        ([key, value]) =>
          !rule.fields.includes(key) && !(key === "utm" && contactUtm(value)),
      )
    ) {
      return contactError("INVALID_REQUEST", false);
    }
    const additional = Object.fromEntries(
      Object.entries(record.fields).filter(
        ([key, value]) =>
          !CONTACT_FIELDS.includes(key) || typeof value !== "string",
      ),
    );
    const additionalJson = JSON.stringify(additional);
    if (additionalJson.length > 49000)
      return contactError("INVALID_REQUEST", false);

    lock = LockService.getScriptLock();
    if (!lock.tryLock(3000)) return contactError("BUSY", true);
    const sheet = SpreadsheetApp.openById(spreadsheetId).getSheetByName(
      rule.tab,
    );
    if (!sheet || sheet.getMaxColumns() < CONTACT_HEADERS.length) {
      return contactConfigurationError(
        "La pestaña configurada no existe o tiene menos de 12 columnas.",
      );
    }
    const last = sheet.getLastRow();
    const headers = sheet.getRange(1, 1, 1, CONTACT_HEADERS.length);
    if (!last) {
      headers.setValues([CONTACT_HEADERS]);
    } else if (
      headers
        .getValues()[0]
        .slice(0, 10)
        .some((value, i) => value !== CONTACT_HEADERS[i])
    ) {
      return contactConfigurationError(
        "Los primeros diez encabezados de la fila 1 no coinciden.",
      );
    }
    if (
      last > 1 &&
      sheet
        .getRange(2, 1, last - 1, 1)
        .createTextFinder(record.id)
        .matchEntireCell(true)
        .matchCase(true)
        .findNext()
    ) {
      return contactJson({
        success: true,
        submission_id: record.id,
        status: "already_stored",
      });
    }
    const row = [
      record.id,
      record.created_at,
      record.form_id,
      ...CONTACT_FIELDS.map((key) =>
        typeof record.fields[key] === "string" ? record.fields[key] : "",
      ),
      additionalJson,
      record.source_url || "",
      "",
      "",
    ];
    const nextRow = Math.max(1, last) + 1;
    if (nextRow > sheet.getMaxRows())
      sheet.insertRowsAfter(sheet.getMaxRows(), 1);
    const cells = sheet.getRange(nextRow, 1, 1, row.length);
    cells.setNumberFormat("@");
    cells.setValues([row.map(contactCell)]);
    SpreadsheetApp.flush();
    return contactJson({
      success: true,
      submission_id: record.id,
      status: "stored",
    });
  } catch (_) {
    return contactError("TEMPORARY", true);
  } finally {
    if (lock && lock.hasLock()) lock.releaseLock();
  }
}

function contactRecord(record) {
  if (
    !contactObject(record) ||
    Object.keys(record).some(
      (key) =>
        ![
          "id",
          "form_id",
          "created_at",
          "fields",
          "source_url",
          "route",
        ].includes(key),
    ) ||
    typeof record.id !== "string" ||
    !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(
      record.id,
    ) ||
    typeof record.form_id !== "string" ||
    !/^[a-z][a-z0-9_-]{0,63}$/.test(record.form_id) ||
    typeof record.created_at !== "string" ||
    !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(record.created_at) ||
    !Number.isFinite(Date.parse(record.created_at)) ||
    new Date(record.created_at).toISOString().replace(".000Z", "Z") !==
      record.created_at ||
    !contactObject(record.fields) ||
    Object.keys(record.fields).filter((key) => key !== "utm").length > 64
  )
    return false;
  if (
    contactOwn(record, "route") &&
    (typeof record.route !== "string" ||
      !/^[a-z][a-z0-9_-]{0,63}$/.test(record.route))
  )
    return false;
  if (
    contactOwn(record, "source_url") &&
    (typeof record.source_url !== "string" ||
      record.source_url.length > 2048 ||
      (record.source_url !== "" &&
        !/^https?:\/\/[^\s]+$/i.test(record.source_url)))
  ) {
    return false;
  }
  return Object.entries(record.fields).every(([key, value]) => {
    if (!contactFieldName(key)) return false;
    return Array.isArray(value)
      ? value.length <= 32 && value.every(contactScalar)
      : contactScalar(value);
  });
}

function contactUtm(value) {
  if (typeof value !== "string" || value.length > 8192) return false;
  const seen = new Set();
  return value.split("&").every((pair) => {
    const match = /^([a-z_]+)=((?:[A-Za-z0-9_.~-]|%[0-9A-F]{2})+)$/.exec(pair);
    if (!match || !CONTACT_UTM_KEYS.includes(match[1]) || seen.has(match[1]))
      return false;
    try {
      const decoded = decodeURIComponent(match[2]);
      if (
        !decoded ||
        /[\x00-\x1f\x7f-\x9f]/.test(decoded) ||
        match[2].replace(/%[0-9A-F]{2}/g, "x").length > 256
      )
        return false;
    } catch (_) {
      return false;
    }
    seen.add(match[1]);
    return true;
  });
}

function contactFieldName(key) {
  return (
    typeof key === "string" &&
    /^[a-z][a-z0-9_]{0,63}$/.test(key) &&
    !["constructor", "prototype"].includes(key)
  );
}

function contactScalar(value) {
  return typeof value === "string"
    ? value.length <= 20000 && !/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/.test(value)
    : typeof value === "boolean" || Number.isFinite(value);
}

function contactOwn(value, key) {
  return Object.prototype.hasOwnProperty.call(value, key);
}

function contactObject(value) {
  return value !== null && typeof value === "object" && !Array.isArray(value);
}

function contactCell(value) {
  return /^[\s\u200B-\u200F\u202A-\u202E\u2060\uFEFF]*[=+\-@＝＋－＠]|^[\t\r\n]/u.test(
    value,
  )
    ? "'" + value
    : value;
}

function contactToken(expected, supplied) {
  if (typeof supplied !== "string" || supplied.length > 256) return false;
  const digest = (value) =>
    Utilities.computeDigest(
      Utilities.DigestAlgorithm.SHA_256,
      value,
      Utilities.Charset.UTF_8,
    );
  const first = digest(expected);
  const second = digest(supplied);
  return first.reduce((diff, item, i) => diff | (item ^ second[i]), 0) === 0;
}

function contactError(code, retryable) {
  return contactJson({ success: false, code, retryable });
}

function contactJson(value) {
  return ContentService.createTextOutput(JSON.stringify(value)).setMimeType(
    ContentService.MimeType.JSON,
  );
}

function contactConfigurationError(reason) {
  console.error("Google Sheets: " + reason);
  return contactError("CONFIGURATION", false);
}
