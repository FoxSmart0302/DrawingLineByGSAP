
// var  points1 = getPoints(document.getElementById("polygon").getAttribute("d"));

// console.log("points1", points1);

let handTl;
let indexCount = 0;
function onConvert() {  
  if(handTl != null){
    handTl.kill();
  }
  const newTimeline = gsap.timeline({ repeat: -1, paused: true, defaults: { ease: "none" } });
  // Create a new timeline for drawing by hand only
  handTl = newTimeline;
  gsap.registerPlugin(DrawSVGPlugin, MotionPathPlugin);

  // gsap.set("#svg path", {stroke:"black", drawSVG:0});
  gsap.set("#handwriting path", { stroke: "black", drawSVG: false });
  gsap.set("#hand", { yPercent: -100, transformOrigin: "center center" });

  gsap.config({ trialWarn: false });
  // const iconTl = gsap.timeline({ reversed: true, paused: true, defaults: { ease: "none", duration: 0.35 } });
  // iconTl.to("#hand", { autoAlpha: 0 }, 0);

  var elem = document.getElementById("handwriting");
  var paths = elem.getElementsByTagName("path");
  for (let i = 0; i < paths.length; i++) {
    paths[i].setAttribute("fill", "rgb(255, 255, 255)");
    paths[i].setAttribute("stroke", "rgb(0, 0, 0)");
    paths[i].setAttribute("stroke-linecap", "round");
    paths[i].setAttribute("stroke-miterlimit", "100");
    paths[i].setAttribute("stroke-width", "1");
  }

  // main timeline creation
  console.log("===pathlength:", paths.length);
  // Calculate the total width of all paths
  
  const totalDrawingTime = 25;

  // Loop through each path
  for (let i = 0; i < paths.length; i++) {
    const pathitem = paths[i];

    // console.log("pathitme", pathitem)
    const pathSize = pathitem.getBBox();

    // Calculate the time it should take to draw this path based on the desired speed
    const itemTime = pathSize.width / totalDrawingTime;

    console.log(`pathSize :>> `, pathSize.width);
    console.log(`itemTime :>> `, itemTime);
    // handT.to(pathitem, { duration: itemTime, drawSVG: true });
    handTl.to("#hand", {
      duration: itemTime,
      drawSVG: true,
      motionPath: { path: pathitem, align: pathitem, autoRoate: true, drawSVG: true },
      onComplete: function (){
        InitCanvas();
      }
    });
    
    const handElement = document.getElementById("hand");
    const position = handElement.getBoundingClientRect();
    handTl.delay({ delay: 2});
    handTl.play(); // play the main timeline after setting up the animations

  }
}
function InitCanvas() {
  const c = document.getElementById("myCanvas");
  const ctx = c.getContext("2d");
  ctx.clearRect(0, 0, c.width, c.height);
  indexCount = 0;
}


$(document).ready(function () {
  let firstE;
  let firstF;
  let prevE;
  let prevF;
  setInterval(() => {
  
    // console.log("current pos", document.getElementById("hand").getAttribute("transform"))
    const transformString = document.getElementById("hand").getAttribute("transform");
    // console.log('transformString :>> ', transformString);
    // Create a DOMMatrix object from the transform string
    const matrix = new DOMMatrix(transformString);

    // Access individual values
    const e = matrix.e;
    const f = matrix.f + 155;


    const c = document.getElementById("myCanvas");
    const ctx = c.getContext("2d");
    ctx.beginPath();
    if (indexCount == 0) {
      firstE = e;
      firstF = f;
      console.log("continue", firstE, firstF);
    } else {
      // ctx.bezierCurveTo(20, 100, 200, 100, 200, 20);
      // console.log("Math:", Math.sqrt(Math.pow(Math.abs(e - prevE), 2) + Math.pow(Math.abs(f - prevF), 2)))
      if (Math.sqrt(Math.pow(Math.abs(e - prevE), 2) + Math.pow(Math.abs(f - prevF), 2)) < 5) {
        ctx.moveTo(prevE, prevF)
        ctx.lineTo(e, f);
        ctx.stroke();
        ctx.fill();
      }
      else{
        
        if (Math.sqrt(Math.pow(Math.abs(prevE - firstE), 2) + Math.pow(Math.abs(prevF - firstF), 2)) < 20 && indexCount > 2) {
          ctx.moveTo(prevE, prevF);
          ctx.lineTo(firstE, firstF);
          ctx.stroke();
          ctx.fill();
  
        }
        firstE = e;
        firstF = f;
      }

    }
    prevE = e;
    prevF = f;

    indexCount ++ ;
  }, 0.001);
})

onConvert();


