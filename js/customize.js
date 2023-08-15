
let handTl;
let indexCount = 0;
function onConvert() {
  InitCanvas();
  if (handTl != null) {
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
    paths[i].setAttribute("fill", "none");
  }

  // main timeline creation
  // Calculate the total width of all paths

  const totalDrawingTime = 25;

  // Loop through each path
  for (let i = 0; i < paths.length; i++) {
    const pathitem = paths[i];

    const pathSize = pathitem.getBBox();

    // Calculate the time it should take to draw this path based on the desired speed
    const itemTime = pathSize.width / totalDrawingTime;

    handTl.to("#hand", {
      duration: itemTime,
      drawSVG: true,
      motionPath: { path: pathitem, align: pathitem, autoRoate: true, drawSVG: true },
      onComplete: function () {
        InitCanvas();
      }
    });

    const handElement = document.getElementById("hand");
    const position = handElement.getBoundingClientRect();
    handTl.delay({ delay: 2 });
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
    const transformString = document.getElementById("hand").getAttribute("transform");
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
    } else {
      // ctx.bezierCurveTo(20, 100, 200, 100, 200, 20);
      if (Math.sqrt(Math.pow(Math.abs(e - prevE), 2) + Math.pow(Math.abs(f - prevF), 2)) < 5) {
        ctx.moveTo(prevE, prevF)
        ctx.lineTo(e, f);
        ctx.stroke();
        ctx.fill();
      }
      else {
        if (Math.sqrt(Math.pow(Math.abs(prevE - firstE), 2) + Math.pow(Math.abs(prevF - firstF), 2)) < 25 && indexCount > 1) {
          // Set the starting and ending coordinates of the line
          var startX = prevE;
          var startY = prevF;
          var endX = firstE;
          var endY = firstF;

          // Set the animation duration and calculate the distance to be covered per frame
          var duration = 100; // 1 second
          var distanceX = (endX - startX) / duration;
          var distanceY = (endY - startY) / duration;

          // Set the current position of the line
          var currentX = startX;
          var currentY = startY;

          // Set the start time for the animation
          var startTime = null;

          // Function to draw the line with animation
          function drawLine(timestamp) {
            if (!startTime) startTime = timestamp;
            var elapsed = timestamp - startTime;

            // Calculate the new position of the line based on the elapsed time
            currentX = startX + (distanceX * elapsed);
            currentY = startY + (distanceY * elapsed);

            // Draw the line
            ctx.beginPath();
            ctx.moveTo(startX, startY);
            ctx.lineTo(currentX, currentY);
            ctx.stroke();
            ctx.fill();

            // Check if the animation is still in progress
            if (elapsed < duration) {
              // Request the next frame
              requestAnimationFrame(drawLine);
            }
          }

          // Call the drawLine function to start the animation
          requestAnimationFrame(drawLine);
        }
        firstE = e;
        firstF = f;
      }
    }
    prevE = e;
    prevF = f;

    indexCount++;
  }, 0.0001);
})

onConvert();


