


function importData() {
  let input = document.createElement('input');
  let fileName = '';
  input.type = 'file';
  input.onchange = async _ => {
    // you can use this method to get file and perform respective operations
    let files = Array.from(input.files);
    
    console.log("files:", files);
    //file Upload
    await uploadFile(files[0]);

    fileName = files[0].name;
    let splitName = fileName.split(/[,.]/);
    console.log("fileName:", fileName.split(/[,.]/));
    let paramArray = [];
    let inputfile = "./image/" + fileName;
    let outfile = "./svg/" + splitName[0] + ".svg";
    paramArray.push(inputfile);
    paramArray.push(outfile);
    console.log("paramArray:", paramArray);
    
    $.ajax({
      type: "POST",
      url: 'main.php',
      dataType: 'json',
      encode: true,
      data: { functionname: 'onConvert', arguments: paramArray },
    }).done(function (response) {
      console.log("php receive successfully");
     
      var svgContainer = document.getElementById("svgContainer");
      // Fetch the SVG file
      fetch(outfile)
        .then(response => response.text())
        .then(svgContent => {
          // Set the SVG file content as the innerHTML of the div
          svgContainer.innerHTML = svgContent;
          onConvert();
        })
        .catch(error => {
          console.error("Error fetching SVG file:", error);
        });
    });

  };
  input.click();

}

async function uploadFile(mfile) {
  let formData = new FormData();
  console.log("mfile:", mfile);
  formData.append('file', mfile);
  await fetch('upload.php', {
    method: 'POST',
    body: formData
  });
  // alert('The file has been uploaded successfully.');
}

