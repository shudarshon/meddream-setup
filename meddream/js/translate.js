$(document).ready(function () {
    var userLanguage = "";
   if (!localStorage.userLanguage) {
       language_complete = navigator.language.split("-");
       language = (language_complete[0]);
       localStorage.setItem('userLanguage', language);
   }
    language = localStorage.userLanguage;
    i18next.use(window.i18nextXHRBackend);
    i18next.use(window.i18nextBrowserLanguageDetector);
    i18next.use(window.jqueryI18next);

    jqueryI18next.init(i18next, $, {
        tName: 't', // --> appends $.t = i18next.t
        i18nName: 'i18n', // --> appends $.i18n = i18next
        handleName: 'localize', // --> appends $(selector).localize(opts);
        selectorAttr: 'data-i18n', // selector for translating elements
        targetAttr: 'data-i18n-target', // element attribute to grab target element to translate (if diffrent then itself)
        optionsAttr: 'data-i18n-options', // element attribute that contains options, will load/set if useOptionsAttr = true
        useOptionsAttr: false, // see optionsAttr
        parseDefaultValueFromContent: true // parses default values from content ele.val or ele.text
    });

    init();
    $('#' + language).prop("checked", true);
    $('.' + language).addClass("active");

    $('label input[type=radio][name=language]').change(function () {
        $(this).find('input[type=radio]').prop("checked", true);
        localStorage.setItem('userLanguage', $(this).val());
        setLanguageToBackEnd($(this).val());
        init();
    });

});


function translate() {
    $(document).localize();
}

function setLanguageToBackEnd(language) {
    var vars = "cmd=setLanguage&lan=" + language;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "Routes.php");
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send(vars);
}

function init() {
    i18next.init({
        debug: false,
        fallbackLng: "en",
        detection: {
            order: ["localStorage"],
            lookupLocalStorage: 'userLanguage',
            caches: ['localStorage']
        },
        backend: {
            loadPath: "locales/{{lng}}/{{ns}}.json"
        }
    }, function () {
        translate();
    });
}

