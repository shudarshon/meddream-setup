/**
 * Created by Seima on 15.2.23.
 */
var DT = (function ($, DT) {

    "use strict";

    /**
     * View port sukurimas
     * @param id  // data id
     * @param w   // width
     * @param h   // height
     * @returns HTML
     * @access bublic
     */


    DT.dumpDicomDataSet =  function(dataSet) {
        // the dataSet.elements object contains properties for each element parsed.  The name of the property
        // is based on the elements tag and looks like 'xGGGGEEEE' where GGGG is the group number and EEEE is the
        // element number both with lowercase hexadecimal letters.  For example, the Series Description DICOM element 0008,103E would
        // be named 'x0008103e'.  Here we iterate over each property (element) so we can build a string describing its
        // contents to add to the output array

            var output = [];
            for (var propertyName in dataSet.elements) {
                var element = dataSet.elements[propertyName];

                // The output string begins with the element tag, length and VR (if present).  VR is undefined for
                // implicit transfer syntaxes
                var text = element.tag;
                text += " length=" + element.length;

                if (element.hadUndefinedLength) {
                    text += " <strong>(-1)</strong>";
                }
                text += "; ";

                if (element.vr) {
                    text += " VR=" + element.vr + "; ";
                }

                var color = 'black';

                // Here we check for Sequence items and iterate over them if present.  items will not be set in the
                // element object for elements that don't have SQ VR type.  Note that implicit little endian
                // sequences will are currently not parsed.
                if (element.items) {
                    output.push('<li>' + text + '</li>');
                    output.push('<ul>');

                    // each item contains its own data set so we iterate over the items
                    // and recursively call this function
                    var itemNumber = 0;
                    element.items.forEach(function (item) {
                        output.push('<li>Item #' + itemNumber++ + ' ' + item.tag + '</li>')
                        output.push('<ul>');
                        DT.dumpDicomDataSet(item.dataSet, output);
                        output.push('</ul>');
                    });
                    output.push('</ul>');
                }
                else if (element.fragments) {
                    output.push('<li>' + text + '</li>');
                    output.push('<ul>');

                    // each item contains its own data set so we iterate over the items
                    // and recursively call this function
                    var itemNumber = 0;
                    element.fragments.forEach(function (fragment) {
                        var basicOffset;
                        if(element.basicOffsetTable) {
                            basicOffset = element.basicOffsetTable[itemNumber];
                        }

                        var str = '<li>Fragment #' + itemNumber++ + ' offset = ' + fragment.offset;
                        str += '(' + basicOffset + ')';
                        str += '; length = ' + fragment.length + '</li>';
                        output.push(str);
                    });
                    output.push('</ul>');
                }
                else {


                    // if the length of the element is less than 128 we try to show it.  We put this check in
                    // to avoid displaying large strings which makes it harder to use.
                    if (element.length < 128) {
                        // Since the dataset might be encoded using implicit transfer syntax and we aren't using
                        // a data dictionary, we need some simple logic to figure out what data types these
                        // elements might be.  Since the dataset might also be explicit we could be switch on the
                        // VR and do a better job on this, perhaps we can do that in another example

                        // First we check to see if the element's length is appropriate for a UI or US VR.
                        // US is an important type because it is used for the
                        // image Rows and Columns so that is why those are assumed over other VR types.
                        if (element.length === 2) {
                            text += " (" + dataSet.uint16(propertyName) + ")";
                        }
                        else if (element.length === 4) {
                            text += " (" + dataSet.uint32(propertyName) + ")";
                        }

                        // Next we ask the dataset to give us the element's data in string form.  Most elements are
                        // strings but some aren't so we do a quick check to make sure it actually has all ascii
                        // characters so we know it is reasonable to display it.
                        var str = dataSet.string(propertyName);
                        var stringIsAscii = isASCII(str);

                        if (stringIsAscii) {
                            // the string will be undefined if the element is present but has no data
                            // (i.e. attribute is of type 2 or 3 ) so we only display the string if it has
                            // data.  Note that the length of the element will be 0 to indicate "no data"
                            // so we don't put anything here for the value in that case.
                            if (str !== undefined) {
                                text += '"' + str + '"';
                            }
                        }
                        else {
                            if (element.length !== 2 && element.length !== 4) {
                                color = '#C8C8C8';
                                // If it is some other length and we have no string
                                text += "<i>binary data</i>";
                            }
                        }

                        if (element.length === 0) {
                            color = '#C8C8C8';
                        }

                    }
                    else {
                        color = '#C8C8C8';

                        // Add text saying the data is too long to show...
                        text += "<i>data too long to show</i>";
                    }
                    // finally we add the string to our output array surrounded by li elements so it shows up in the
                    // DOM as a list
                    output.push('<li style="color:' + color + ';">' + text + '</li>');

                }
            }

            var parser = new DOMParser();
            var doc = parser.parseFromString('<ul>' + output.join('') + '</ul>', "text/xml");
            console.log(doc);


    };

    function
        isASCII(str) {
        return /^[\x00-\x7F]*$/.test(str);
    }

    DT.printAllDicomTags = function(dataSet)
    {
        for (var propertyName in dataSet.elements) {
            var element = dataSet.elements[propertyName];
            console.log('name', propertyName, element.vr);
        }
    }

    return DT;

}($, DT || {}));