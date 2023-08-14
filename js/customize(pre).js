function onConvert() {
  let speed = 0.5;
  const itemSpeed = 100;

  // Create a new timeline for drawing by hand only
  const handTl = gsap.timeline({ repeat: -1, paused: true, defaults: { ease: "none" } });
  const handTl1 = gsap.timeline({ repeat: -1, paused: true, defaults: { ease: "none" } });
  // const tl = gsap.timeline({defaults:{ease:"none"}});
  // tl.timeScale(speed.toFixed(2));
  gsap.registerPlugin(DrawSVGPlugin, MotionPathPlugin);

  // gsap.set("#svg path", {stroke:"black", drawSVG:0});
  gsap.set("#handwriting path", { stroke: "black", drawSVG: false });
  gsap.set("#hand", { yPercent: -100, transformOrigin: "center center" });

  gsap.config({ trialWarn: false });
  const iconTl = gsap.timeline({ reversed: true, paused: true, defaults: { ease: "none", duration: 0.35 } });
  iconTl.to("#hand", { autoAlpha: 0 }, 0);

  var elem = document.getElementById("handwriting");
  var paths = elem.getElementsByTagName("path");
  for (let i = 0; i < paths.length; i++) {
    paths[i].setAttribute("fill", "rgb(255, 255, 255)");
    paths[i].setAttribute("stroke", "rgb(0, 0, 0)");
    paths[i].setAttribute("stroke-linecap", "round");
    paths[i].setAttribute("stroke-miterlimit", "100");
    paths[i].setAttribute("stroke-width", "5");
  }

  // main timeline creation
  console.log("===pathlength:", paths.length);
  // Calculate the total width of all paths
  let totalPathWidth = 0;
  for (let i = 0; i < paths.length; i++) {
    const pathSize = paths[i].getBBox();
    totalPathWidth += pathSize.width;
  }

  // Calculate the desired constant speed
  const totalDrawingTime = 10;
  const constantSpeed = totalPathWidth / totalDrawingTime;

  // Loop through each path
  for (let i = 0; i < paths.length; i++) {
    const pathitem = paths[i];
    // var pathData = pathitem.getAttribute("d");
    // var pathVector = pathData.split(/[\s,]+/).map(Number);
    // console.log("pathVector", pathVector);


    const pathSize = pathitem.getBBox();

    // Calculate the time it should take to draw this path based on the desired speed
    const itemTime = pathSize.width / constantSpeed;

    // console.log(`pathitem`, pathitem);

    // handTl.to(pathitem, { duration: itemTime, drawSVG: true });
    handTl.fromTo(pathitem, {drawSVG: "50 0%"}, {duration: itemTime, drawSVG:"0 50%"}); 
    handTl1.to("#hand", {
      duration: itemTime,
      drawSVG: true,
      motionPath: { path: pathitem, align: pathitem, autoRoate: true },
    });
    handTl.play(); // play the main timeline after setting up the animations
    handTl1.play();

    gsap.set('#line', { autoAlpha: 1 })
  }
}

function onConvert1() {
  gsap.set("#line", { autoAlpha: 1 });
  var action = gsap.timeline({ repeat: -1, repeatDelay: 1 })
    .set('#dot', { autoAlpha: 1 })
    .from('#line', { drawSVG: '0', duration: 6, ease: 'none' })
    .to("#dot", {
      motionPath: {
        path: "#line",
        align: "#line",
        alignOrigin: [0.5, 0.5]
      },
      duration: 6, ease: 'none'
    }, 0)
}

onConvert();


