var SID = '';
var CONFIG = {
  KEYS_ROW: 1
};
var CONFIG_DEFAULT = JSON.parse(JSON.stringify(CONFIG));
function configManip(config, callback) {
  objectAssign(CONFIG, config);
  callback();
  objectAssign(CONFIG, CONFIG_DEFAULT);
}

/**
 * the "e" argument represents an event parameter that can contain information about any URL parameters.
 * refs. https://developers.google.com/apps-script/guides/web
 */
function makeObject(e) {
  var obj = {};

  configManip({KEYS_ROW: 3}, function() {
    obj.sample = getSheetData('_status');
  });

  return obj;
}

function sheetParserBasic(rows) {
  var result = [];

  var keys = rows;
  rows = keys.splice(CONFIG.KEYS_ROW);
  keys = keys.pop();

  if (keys[0] === '#') keys[0] = 'index';
  rows.forEach(function(row) {
    if (empty(row[1])) return;
    var record = {};
    keys.forEach(function(key, colIdx) {
      record[key] = row[colIdx];
    });
    result.push(record);
  });

  return result;
}

function getSheetData(sheetName, sheetParser) {
  if (sheetParser == null) sheetParser = sheetParserBasic;
  var sheet = SpreadsheetApp.openById(SID).getSheetByName(sheetName);
  if (sheet == null) return null;
  var rows = sheet.getDataRange().getValues();
  var result = sheetParser(rows);

  return result;
}

function doGet(e) {
  return doMain(e);
}

function doPost(e) {
  return doMain(e);
}

function doMain(e) {
  return makeContent(makeResponse(e));
}

function makeResponse(e) {
  var data = makeObject(e);
  var json = JSON.stringify(data, null, 2);

  var useJsonp = !empty(e.parameter.callback);
  var response = {};
  response.mime = useJsonp ? ContentService.MimeType.JAVASCRIPT : ContentService.MimeType.JSON;
  response.content = useJsonp ? sprintf('%s(%s);', e.parameter.callback, json) : json;

  return response;
}

function makeContent(response) {
  return ContentService.createTextOutput(response.content).setMimeType(response.mime);
}

function sprintf(format, args) {
  var p = 1, params = arguments;
  return format.replace(/%./g, function(m) {
    if (m === '%%') return '%';
    if (m === '%s') return params[p++];
    return m;
  });
}

function empty(arg) {
  return arg == null || arg === '';
}

function castBoolean(arg) {
  return arg === true;
}

function isNumericString(arg) {
  return String(arg) === String(Number(arg));
}

function isInteger(arg) {
  return typeof arg === 'number' && isFinite(arg) && Math.floor(arg) === arg;
}

function objectAssign(assignee, assigner) {
  Object.keys(assigner).forEach(function(key) {
    assignee[key] = assigner[key];
  });
  return assignee;
}

function sortGoogleSheets() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheets = ss.getSheets();
  var sheetNames = [];

  sheets.forEach(function(sheet) {
    sheetNames.push(sheet.getName());
  });

  sheetNames.sort(function(a, b) {
    if (!isNumericString(a) && isNumericString(b)) return -1;
    if (isNumericString(a) && !isNumericString(b)) return 1;
    if (isNumericString(a) && isNumericString(b)) {
      a = Number(a);
      b = Number(b);
    }
    if (a < b) return -1;
    if (a > b) return 1;
    return 0;
  });

  sheetNames.forEach(function(sheetName, i) {
    ss.setActiveSheet(ss.getSheetByName(sheetName));
    ss.moveActiveSheet(i + 1);
  });
}
