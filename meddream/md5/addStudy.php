<!DOCTYPE html>
<html>
    <head>
        <script>
            function addStudyToMedMream(uuid)
            {
                if(typeof(Storage) !== "undefined" && localStorage.viewerIsOpen !== "undefined" && localStorage.viewerIsOpen === 'true') {

                   // var tmp = typeof(localStorage.studyList) !== "undefined" ? JSON.parse(localStorage.studyList) : [];
                   //     tmp.push(uuid);
                        localStorage.setItem("addStudy", uuid); //JSON.stringify(tmp)
                        //window.open('javascript:void window.focus()', 'MedDreamViewer', '');
                        //todo padaryti fokusavima
                }
                else
                {
                    var medDreamWindow = window.open("index.html?study=" + uuid, "MedDreamViewer", "");
                    medDreamWindow.focus();
                }
                window.self.close();

            }
        </script>
    </head>
    <body onload="addStudyToMedMream('<?php echo $_GET["study"]; ?>');">
    </body>
</html>
