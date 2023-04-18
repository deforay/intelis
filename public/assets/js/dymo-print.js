function urldecode(str) { if (typeof str != "string") { return str; } return decodeURIComponent(str.replace(/\+/g, ' ')); }

function printBarcodeLabel(barcode,facility){
        
           
            $.blockUI();
            dymo.label.framework.trace = 1; //true
            facility = urldecode(facility);

            try{
                // open label
              
                
                      var label = dymo.label.framework.openLabelXml(dymoLabelXml);
  
                      // set label text
                      if(dymoLabelXml.indexOf("<Name>BARCODE</Name>") !== -1){
                        label.setObjectText('BARCODE', barcode);
                      }
                      if(dymoLabelXml.indexOf("<Name>TEXT</Name>") !== -1){
                        label.setObjectText('TEXT', facility);
                      }
                      
                      
                      // select printer to print on
                      // for simplicity sake just use the first LabelWriter printer
                      var printers = dymo.label.framework.getPrinters();
                      if (printers.length === 0)
                          throw "No DYMO printers are installed. Install DYMO printers.";
  
                      var printerName = "";
                      for (var i = 0; i < printers.length; ++i)
                      {
                          var printer = printers[i];
                          if (printer.printerType == "LabelWriterPrinter")
                          {
                              printerName = printer.name;
                              break;
                          }
                      }
  
                      if (printerName === "")
                          throw "No LabelWriter printers found. Install LabelWriter printer";
  
                      // finally print the label
                      label.print(printerName);
                
              
            }
            catch(e){
                alert(e.message || e);
            }
            $.unblockUI();
        
    }