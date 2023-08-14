<?php

namespace Potracio;

class Point
{
  public $x;
  public $y;

  public function __construct($x = NULL, $y = NULL)
  {
    if ($x !== NULL)
      $this->x = $x;
    if ($y !== NULL)
      $this->y = $y;
  }
}

class Opti
{
  public $pen = 0;
  public $c;
  public $t = 0;
  public $s = 0;
  public $alpha = 0;

  public function __construct()
  {
    $this->c = array(new Point(), new Point());
  }
}

class Bitmap
{
  public $w;
  public $h;
  public $size;
  public $data;

  public function __construct($w, $h)
  {
    $this->w = $w;
    $this->h = $h;
    $this->size = $w * $h;
    $this->data = array();
  }

  public function at($x, $y)
  {
    return ($x >= 0 && $x < $this->w && $y >= 0 && $y < $this->h) &&
      $this->data[$this->w * $y + $x] === 1;
  }

  public function index($i)
  {
    $point = new Point();
    $point->y = floor($i / $this->w);
    $point->x = $i - $point->y * $this->w;
    return $point;
  }

  public function flip($x, $y)
  {
    if ($this->at($x, $y)) {
      $this->data[$this->w * $y + $x] = 0;
    } else {
      $this->data[$this->w * $y + $x] = 1;
    }
  }
}

class Path
{
  public $area = 0;
  public $len = 0;
  public $curve = array();
  public $pt = array();
  public $minX = 100000;
  public $minY = 100000;
  public $maxX = -1;
  public $maxY = -1;
  public $sum = array();
  public $lon = array();
}

class Curve
{
  public $n;
  public $tag;
  public $c;
  public $alphaCurve = 0;
  public $vertex;
  public $alpha;
  public $alpha0;
  public $beta;

  public function __construct($n)
  {
    $this->n = $n;
    $this->tag = array_fill(0, $n, NULL);
    $this->c = array_fill(0, $n * 3, NULL);
    $this->vertex = array_fill(0, $n, NULL);
    $this->alpha = array_fill(0, $n, NULL);
    $this->alpha0 = array_fill(0, $n, NULL);
    $this->beta = array_fill(0, $n, NULL);
  }
}

class Quad
{
  public $data = array(0, 0, 0, 0, 0, 0, 0, 0, 0);

  public function at($x, $y)
  {
    return $this->data[$x * 3 + $y];
  }
}

class Sum
{
  public $x;
  public $y;
  public $xy;
  public $x2;
  public $y2;

  public function __construct($x, $y, $xy, $x2, $y2)
  {
    $this->x = $x;
    $this->y = $y;
    $this->xy = $xy;
    $this->x2 = $x2;
    $this->y2 = $y2;
  }
}

class Potracio
{
  public $imgElement;
  public $imgCanvas;
  public $bm = NULL;
  public $pathlist = array();
  public $info = array(
    'turnpolicy' => "minority",
    'turdsize' => 2,
    'optcurve' => TRUE,
    'alphamax' => 1,
    'opttolerance' => 0.2
  );

  public function __construct($data = array())
  {
    $this->setParameter($data);
  }

  public function setParameter($data)
  {
    $this->info = (object) array_merge((array) $this->info, $data);
  }

  public function loadImageFromFile($file)
  {
    list($w, $h) = getimagesize($file);
    $image = imagecreatefromstring(file_get_contents($file));

    $this->bm = new Bitmap($w, $h);

    for ($i = 0; $i < $h; $i++) {
      for ($j = 0; $j < $w; $j++) {
        $rgb = imagecolorat($image, $j, $i);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $color = (0.2126 * $r) + (0.7153 * $g) + (0.0721 * $b);
        $this->bm->data[] = $color < 128 ? 1 : 0;
      }
    }
  }

  private function bmToPathlist()
  {
    $info = $this->info;
    $bm = &$this->bm;
    $bm1 = clone $bm;
    $currentPoint = new Point(0, 0);

    $findNext = function ($point) use ($bm1) {
      $i = $bm1->w * $point->y + $point->x;
      while ($i < $bm1->size && $bm1->data[$i] !== 1) {
        $i++;
      }
      if ($i < $bm1->size)
        return $bm1->index($i);
      return false;
    };

    $majority = function ($x, $y) use ($bm1) {
      for ($i = 2; $i < 5; $i++) {
        $ct = 0;
        for ($a = -$i + 1; $a <= $i - 1; $a++) {
          $ct += $bm1->at($x + $a, $y + $i - 1) ? 1 : -1;
          $ct += $bm1->at($x + $i - 1, $y + $a - 1) ? 1 : -1;
          $ct += $bm1->at($x + $a - 1, $y - $i) ? 1 : -1;
          $ct += $bm1->at($x - $i, $y + $a) ? 1 : -1;
        }
        if ($ct > 0) {
          return 1;
        } else if ($ct < 0) {
          return 0;
        }
      }
      return 0;
    };

    $findPath = function ($point) use ($bm, $bm1, $majority, $info) {
      $path = new Path();
      $x = $point->x;
      $y = $point->y;
      $dirx = 0;
      $diry = 1;

      $path->sign = $bm->at($point->x, $point->y) ? "+" : "-";

      while (1) {
        $path->pt[] = new Point($x, $y);
        if ($x > $path->maxX)
          $path->maxX = $x;
        if ($x < $path->minX)
          $path->minX = $x;
        if ($y > $path->maxY)
          $path->maxY = $y;
        if ($y < $path->minY)
          $path->minY = $y;
        $path->len++;

        $x += $dirx;
        $y += $diry;
        $path->area -= $x * $diry;

        if ($x === $point->x && $y === $point->y)
          break;

        $l = $bm1->at($x + ($dirx + $diry - 1) / 2, $y + ($diry - $dirx - 1) / 2);
        $r = $bm1->at($x + ($dirx - $diry - 1) / 2, $y + ($diry + $dirx - 1) / 2);

        if ($r && !$l) {
          if (
            $info->turnpolicy === "right" ||
            ($info->turnpolicy === "black" && $path->sign === '+') ||
            ($info->turnpolicy === "white" && $path->sign === '-') ||
            ($info->turnpolicy === "majority" && $majority($x, $y)) ||
            ($info->turnpolicy === "minority" && !$majority($x, $y))
          ) {
            $tmp = $dirx;
            $dirx = -$diry;
            $diry = $tmp;
          } else {
            $tmp = $dirx;
            $dirx = $diry;
            $diry = -$tmp;
          }
        } else if ($r) {
          $tmp = $dirx;
          $dirx = -$diry;
          $diry = $tmp;
        } else if (!$l) {
          $tmp = $dirx;
          $dirx = $diry;
          $diry = -$tmp;
        }
      }
      return $path;
    };

    $xorPath = function ($path) use (&$bm1) {
      $y1 = $path->pt[0]->y;
      $len = $path->len;

      for ($i = 1; $i < $len; $i++) {
        $x = $path->pt[$i]->x;
        $y = $path->pt[$i]->y;

        if ($y !== $y1) {
          $minY = $y1 < $y ? $y1 : $y;
          $maxX = $path->maxX;
          for ($j = $x; $j < $maxX; $j++) {
            $bm1->flip($j, $minY);
          }
          $y1 = $y;
        }
      }
    };

    while ($currentPoint = $findNext($currentPoint)) {
      $path = $findPath($currentPoint);

      $xorPath($path);

      if ($path->area > $info->turdsize) {
        $this->pathlist[] = $path;
      }
    }
  }

  private function processPath()
  {
    $info = $this->info;

    $mod = function ($a, $n) {
      return $a >= $n ? $a % $n : ($a >= 0 ? $a : $n - 1 - (-1 - $a) % $n);
    };

    $xprod = function ($p1, $p2) {
      return $p1->x * $p2->y - $p1->y * $p2->x;
    };

    $cyclic = function ($a, $b, $c) {
      if ($a <= $c) {
        return ($a <= $b && $b < $c);
      } else {
        return ($a <= $b || $b < $c);
      }
    };

    $sign = function ($i) {
      return $i > 0 ? 1 : ($i < 0 ? -1 : 0);
    };

    $quadform = function ($Q, $w) {
      $v = array_fill(0, 3, NULL);

      $v[0] = $w->x;
      $v[1] = $w->y;
      $v[2] = 1;
      $sum = 0.0;

      for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 3; $j++) {
          $sum += $v[$i] * $Q->at($i, $j) * $v[$j];
        }
      }
      return $sum;
    };

    $interval = function ($lambda, $a, $b) {
      $res = new Point();

      $res->x = $a->x + $lambda * ($b->x - $a->x);
      $res->y = $a->y + $lambda * ($b->y - $a->y);
      return $res;
    };

    $dorth_infty = function ($p0, $p2) use ($sign) {
      $r = new Point();

      $r->y = $sign($p2->x - $p0->x);
      $r->x = -$sign($p2->y - $p0->y);

      return $r;
    };

    $ddenom = function ($p0, $p2) use ($dorth_infty) {
      $r = $dorth_infty($p0, $p2);

      return $r->y * ($p2->x - $p0->x) - $r->x * ($p2->y - $p0->y);
    };

    $dpara = function ($p0, $p1, $p2) {
      $x1 = $p1->x - $p0->x;
      $y1 = $p1->y - $p0->y;
      $x2 = $p2->x - $p0->x;
      $y2 = $p2->y - $p0->y;

      return $x1 * $y2 - $x2 * $y1;
    };

    $cprod = function ($p0, $p1, $p2, $p3) {
      $x1 = $p1->x - $p0->x;
      $y1 = $p1->y - $p0->y;
      $x2 = $p3->x - $p2->x;
      $y2 = $p3->y - $p2->y;

      return $x1 * $y2 - $x2 * $y1;
    };

    $iprod = function ($p0, $p1, $p2) {
      $x1 = $p1->x - $p0->x;
      $y1 = $p1->y - $p0->y;
      $x2 = $p2->x - $p0->x;
      $y2 = $p2->y - $p0->y;

      return $x1 * $x2 + $y1 * $y2;
    };

    $iprod1 = function ($p0, $p1, $p2, $p3) {
      $x1 = $p1->x - $p0->x;
      $y1 = $p1->y - $p0->y;
      $x2 = $p3->x - $p2->x;
      $y2 = $p3->y - $p2->y;

      return $x1 * $x2 + $y1 * $y2;
    };

    $ddist = function ($p, $q) {
      return sqrt(($p->x - $q->x) * ($p->x - $q->x) + ($p->y - $q->y) * ($p->y - $q->y));
    };

    $bezier = function ($t, $p0, $p1, $p2, $p3) {
      $s = 1 - $t;
      $res = new Point();

      $res->x = $s * $s * $s * $p0->x
        + 3 * ($s * $s * $t) * $p1->x
        + 3 * ($t * $t * $s) * $p2->x
        + $t * $t * $t * $p3->x;

      $res->y = $s * $s * $s * $p0->y
        + 3 * ($s * $s * $t) * $p1->y
        + 3 * ($t * $t * $s) * $p2->y
        + $t * $t * $t * $p3->y;

      return $res;
    };

    //$tangent function
    $tangent = function ($p0, $p1, $p2, $p3, $q0, $q1) use ($cprod) {
      $A = $cprod($p0, $p1, $q0, $q1);
      $B = $cprod($p1, $p2, $q0, $q1);
      $C = $cprod($p2, $p3, $q0, $q1);
      $a = $A - 2 * $B + $C;
      $b = -2 * $A + 2 * $B;
      $c = $A;

      $d = $b * $b - 4 * $a * $c;

      if ($a == 0 || $d < 0) {
        return -1.0;
      }

      $s = sqrt($d);

      if ($a == 0) {
        return -1.0;
      }
      $r1 = (-$b + $s) / (2 * $a);
      $r2 = (-$b - $s) / (2 * $a);

      if ($r1 >= 0 && $r1 <= 1) {
        return $r1;
      } else if ($r2 >= 0 && $r2 <= 1) {
        return $r2;
      } else {
        return -1.0;
      }
    };

    //calcSums function
    $calcSums = function (&$path) {
      $path->x0 = $path->pt[0]->x;
      $path->y0 = $path->pt[0]->y;

      $path->sums = array();
      $s = &$path->sums;
      $s[] = new Sum(0, 0, 0, 0, 0);
      for ($i = 0; $i < $path->len; $i++) {
        $x = $path->pt[$i]->x - $path->x0;
        $y = $path->pt[$i]->y - $path->y0;
        $s[] = new Sum(
          $s[$i]->x + $x,
          $s[$i]->y + $y,
          $s[$i]->xy + $x * $y,
          $s[$i]->x2 + $x * $x,
          $s[$i]->y2 + $y * $y
        );
      }
    };

    //calcLon function
    $calcLon = function (&$path) use ($mod, $xprod, $sign, $cyclic) {
      $n = $path->len;
      $pt = &$path->pt;
      $pivk = array_fill(0, $n, NULL);
      $nc = array_fill(0, $n, NULL);
      $ct = array_fill(0, 4, NULL);
      $path->lon = array_fill(0, $n, NULL);

      $constraint = array(new Point(), new Point());
      $cur = new Point();
      $off = new Point();
      $dk = new Point();

      $k = 0;
      for ($i = $n - 1; $i >= 0; $i--) {
        if ($pt[$i]->x != $pt[$k]->x && $pt[$i]->y != $pt[$k]->y) {
          $k = $i + 1;
        }
        $nc[$i] = $k;
      }

      for ($i = $n - 1; $i >= 0; $i--) {
        $ct[0] = $ct[1] = $ct[2] = $ct[3] = 0;
        $dir = (3 + 3 * ($pt[$mod($i + 1, $n)]->x - $pt[$i]->x) +
          ($pt[$mod($i + 1, $n)]->y - $pt[$i]->y)) / 2;
        $ct[$dir]++;

        $constraint[0]->x = 0;
        $constraint[0]->y = 0;
        $constraint[1]->x = 0;
        $constraint[1]->y = 0;

        $k = $nc[$i];
        $k1 = $i;
        while (1) {
          $foundk = 0;
          $dir =  (3 + 3 * $sign($pt[$k]->x - $pt[$k1]->x) +
            $sign($pt[$k]->y - $pt[$k1]->y)) / 2;
          $ct[$dir]++;

          if ($ct[0] && $ct[1] && $ct[2] && $ct[3]) {
            $pivk[$i] = $k1;
            $foundk = 1;
            break;
          }

          $cur->x = $pt[$k]->x - $pt[$i]->x;
          $cur->y = $pt[$k]->y - $pt[$i]->y;

          if ($xprod($constraint[0], $cur) < 0 || $xprod($constraint[1], $cur) > 0) {
            break;
          }

          if (abs($cur->x) <= 1 && abs($cur->y) <= 1) {
          } else {
            $off->x = $cur->x + (($cur->y >= 0 && ($cur->y > 0 || $cur->x < 0)) ? 1 : -1);
            $off->y = $cur->y + (($cur->x <= 0 && ($cur->x < 0 || $cur->y < 0)) ? 1 : -1);
            if ($xprod($constraint[0], $off) >= 0) {
              $constraint[0]->x = $off->x;
              $constraint[0]->y = $off->y;
            }
            $off->x = $cur->x + (($cur->y <= 0 && ($cur->y < 0 || $cur->x < 0)) ? 1 : -1);
            $off->y = $cur->y + (($cur->x >= 0 && ($cur->x > 0 || $cur->y < 0)) ? 1 : -1);
            if ($xprod($constraint[1], $off) <= 0) {
              $constraint[1]->x = $off->x;
              $constraint[1]->y = $off->y;
            }
          }
          $k1 = $k;
          $k = $nc[$k1];
          if (!$cyclic($k, $i, $k1)) {
            break;
          }
        }
        if ($foundk == 0) {
          $dk->x = $sign($pt[$k]->x - $pt[$k1]->x);
          $dk->y = $sign($pt[$k]->y - $pt[$k1]->y);
          $cur->x = $pt[$k1]->x - $pt[$i]->x;
          $cur->y = $pt[$k1]->y - $pt[$i]->y;

          $a = $xprod($constraint[0], $cur);
          $b = $xprod($constraint[0], $dk);
          $c = $xprod($constraint[1], $cur);
          $d = $xprod($constraint[1], $dk);

          $j = 10000000;
          if ($b < 0) {
            $j = floor($a / -$b);
          }
          if ($d > 0) {
            $j = min($j, floor(-$c / $d));
          }
          $pivk[$i] = $mod($k1 + $j, $n);
        }
      }

      $j = $pivk[$n - 1];
      $path->lon[$n - 1] = $j;
      for ($i = $n - 2; $i >= 0; $i--) {
        if ($cyclic($i + 1, $pivk[$i], $j)) {
          $j = $pivk[$i];
        }
        $path->lon[$i] = $j;
      }

      for ($i = $n - 1; $cyclic($mod($i + 1, $n), $j, $path->lon[$i]); $i--) {
        $path->lon[$i] = $j;
      }
    };


    //betPloygon function 
    $bestPolygon = function (&$path) use ($mod) {

      $penalty3 = function ($path, $i, $j) {
        $n = $path->len;
        $pt = $path->pt;
        $sums = $path->sums;
        $r = 0;
        if ($j >= $n) {
          $j -= $n;
          $r = 1;
        }

        if ($r == 0) {
          $x = $sums[$j + 1]->x - $sums[$i]->x;
          $y = $sums[$j + 1]->y - $sums[$i]->y;
          $x2 = $sums[$j + 1]->x2 - $sums[$i]->x2;
          $xy = $sums[$j + 1]->xy - $sums[$i]->xy;
          $y2 = $sums[$j + 1]->y2 - $sums[$i]->y2;
          $k = $j + 1 - $i;
        } else {
          $x = $sums[$j + 1]->x - $sums[$i]->x + $sums[$n]->x;
          $y = $sums[$j + 1]->y - $sums[$i]->y + $sums[$n]->y;
          $x2 = $sums[$j + 1]->x2 - $sums[$i]->x2 + $sums[$n]->x2;
          $xy = $sums[$j + 1]->xy - $sums[$i]->xy + $sums[$n]->xy;
          $y2 = $sums[$j + 1]->y2 - $sums[$i]->y2 + $sums[$n]->y2;
          $k = $j + 1 - $i + $n;
        }

        $px = ($pt[$i]->x + $pt[$j]->x) / 2.0 - $pt[0]->x;
        $py = ($pt[$i]->y + $pt[$j]->y) / 2.0 - $pt[0]->y;
        $ey = ($pt[$j]->x - $pt[$i]->x);
        $ex = - ($pt[$j]->y - $pt[$i]->y);

        $a = (($x2 - 2 * $x * $px) / $k + $px * $px);
        $b = (($xy - $x * $py - $y * $px) / $k + $px * $py);
        $c = (($y2 - 2 * $y * $py) / $k + $py * $py);

        $s = $ex * $ex * $a + 2 * $ex * $ey * $b + $ey * $ey * $c;

        return sqrt($s);
      };

      $n = $path->len;
      $pen = array_fill(0, $n + 1, NULL);
      $prev = array_fill(0, $n + 1, NULL);
      $clip0 = array_fill(0, $n, NULL);
      $clip1 = array_fill(0, $n + 1,  NULL);
      $seg0 = array_fill(0, $n + 1, NULL);
      $seg1 = array_fill(0, $n + 1, NULL);

      for ($i = 0; $i < $n; $i++) {
        $c = $mod($path->lon[$mod($i - 1, $n)] - 1, $n);
        if ($c == $i) {
          $c = $mod($i + 1, $n);
        }
        if ($c < $i) {
          $clip0[$i] = $n;
        } else {
          $clip0[$i] = $c;
        }
      }

      $j = 1;
      for ($i = 0; $i < $n; $i++) {
        while ($j <= $clip0[$i]) {
          $clip1[$j] = $i;
          $j++;
        }
      }

      $i = 0;
      for ($j = 0; $i < $n; $j++) {
        $seg0[$j] = $i;
        $i = $clip0[$i];
      }
      $seg0[$j] = $n;
      $m = $j;

      $i = $n;
      for ($j = $m; $j > 0; $j--) {
        $seg1[$j] = $i;
        $i = $clip1[$i];
      }
      $seg1[0] = 0;

      $pen[0] = 0;
      for ($j = 1; $j <= $m; $j++) {
        for ($i = $seg1[$j]; $i <= $seg0[$j]; $i++) {
          $best = -1;
          for ($k = $seg0[$j - 1]; $k >= $clip1[$i]; $k--) {
            $thispen = $penalty3($path, $k, $i) + $pen[$k];
            if ($best < 0 || $thispen < $best) {
              $prev[$i] = $k;
              $best = $thispen;
            }
          }
          $pen[$i] = $best;
        }
      }
      $path->m = $m;
      $path->po = array_fill(0, $m, NULL);

      for ($i = $n, $j = $m - 1; $i > 0; $j--) {
        $i = $prev[$i];
        $path->po[$j] = $i;
      }
    };

    //adjustVertices function
    $adjustVertices = function (&$path) use ($mod, $quadform) {

      $pointslope = function ($path, $i, $j, &$ctr, &$dir) {

        $n = $path->len;
        $sums = $path->sums;
        $r = 0;

        while ($j >= $n) {
          $j -= $n;
          $r += 1;
        }
        while ($i >= $n) {
          $i -= $n;
          $r -= 1;
        }
        while ($j < 0) {
          $j += $n;
          $r -= 1;
        }
        while ($i < 0) {
          $i += $n;
          $r += 1;
        }

        $x = $sums[$j + 1]->x - $sums[$i]->x + $r * $sums[$n]->x;
        $y = $sums[$j + 1]->y - $sums[$i]->y + $r * $sums[$n]->y;
        $x2 = $sums[$j + 1]->x2 - $sums[$i]->x2 + $r * $sums[$n]->x2;
        $xy = $sums[$j + 1]->xy - $sums[$i]->xy + $r * $sums[$n]->xy;
        $y2 = $sums[$j + 1]->y2 - $sums[$i]->y2 + $r * $sums[$n]->y2;
        $k = $j + 1 - $i + $r * $n;

        $ctr->x = $x / $k;
        $ctr->y = $y / $k;

        $a = ($x2 - $x * $x / $k) / $k;
        $b = ($xy - $x * $y / $k) / $k;
        $c = ($y2 - $y * $y / $k) / $k;

        $lambda2 = ($a + $c + sqrt(($a - $c) * ($a - $c) + 4 * $b * $b)) / 2;

        $a -= $lambda2;
        $c -= $lambda2;

        if (abs($a) >= abs($c)) {
          $l = sqrt($a * $a + $b * $b);
          if ($l != 0) {
            $dir->x = -$b / $l;
            $dir->y = $a / $l;
          }
        } else {
          $l = sqrt($c * $c + $b * $b);
          if ($l !== 0) {
            $dir->x = -$c / $l;
            $dir->y = $b / $l;
          }
        }
        if ($l == 0) {
          $dir->x = $dir->y = 0;
        }
      };

      $m = $path->m;
      $po = $path->po;
      $n = $path->len;
      $pt = $path->pt;
      $x0 = $path->x0;
      $y0 = $path->y0;
      $ctr = array_fill(0, $m, NULL);
      $dir = array_fill(0, $m, NULL);
      $q = array_fill(0, $m, NULL);
      $v = array_fill(0, 3, NULL);
      $s = new Point();

      $path->curve = new Curve($m);

      for ($i = 0; $i < $m; $i++) {
        $j = $po[$mod($i + 1, $m)];
        $j = $mod($j - $po[$i], $n) + $po[$i];
        $ctr[$i] = new Point();
        $dir[$i] = new Point();
        $pointslope($path, $po[$i], $j, $ctr[$i], $dir[$i]);
      }

      for ($i = 0; $i < $m; $i++) {
        $q[$i] = new Quad();
        $d = $dir[$i]->x * $dir[$i]->x + $dir[$i]->y * $dir[$i]->y;
        if ($d == 0.0) {
          for ($j = 0; $j < 3; $j++) {
            for ($k = 0; $k < 3; $k++) {
              $q[$i]->data[$j * 3 + $k] = 0;
            }
          }
        } else {
          $v[0] = $dir[$i]->y;
          $v[1] = -$dir[$i]->x;
          $v[2] = -$v[1] * $ctr[$i]->y - $v[0] * $ctr[$i]->x;
          for ($l = 0; $l < 3; $l++) {
            for ($k = 0; $k < 3; $k++) {
              if ($d != 0) {
                $q[$i]->data[$l * 3 + $k] = $v[$l] * $v[$k] / $d;
              } else {
                $q[$i]->data[$l * 3 + $k] = INF; // TODO Hack para evitar división por 0
              }
            }
          }
        }
      }

      for ($i = 0; $i < $m; $i++) {
        $Q = new Quad();
        $w = new Point();

        $s->x = $pt[$po[$i]]->x - $x0;
        $s->y = $pt[$po[$i]]->y - $y0;

        $j = $mod($i - 1, $m);

        for ($l = 0; $l < 3; $l++) {
          for ($k = 0; $k < 3; $k++) {
            $Q->data[$l * 3 + $k] = $q[$j]->at($l, $k) + $q[$i]->at($l, $k);
          }
        }

        while (1) {

          $det = $Q->at(0, 0) * $Q->at(1, 1) - $Q->at(0, 1) * $Q->at(1, 0);
          if ($det != 0) {
            $w->x = (-$Q->at(0, 2) * $Q->at(1, 1) + $Q->at(1, 2) * $Q->at(0, 1)) / $det;
            $w->y = ($Q->at(0, 2) * $Q->at(1, 0) - $Q->at(1, 2) * $Q->at(0, 0)) / $det;
            break;
          }

          if ($Q->at(0, 0) > $Q->at(1, 1)) {
            $v[0] = -$Q->at(0, 1);
            $v[1] = $Q->at(0, 0);
          } else if ($Q->at(1, 1)) {
            $v[0] = -$Q->at(1, 1);
            $v[1] = $Q->at(1, 0);
          } else {
            $v[0] = 1;
            $v[1] = 0;
          }
          $d = $v[0] * $v[0] + $v[1] * $v[1];
          $v[2] = -$v[1] * $s->y - $v[0] * $s->x;
          for ($l = 0; $l < 3; $l++) {
            for ($k = 0; $k < 3; $k++) {
              $Q->data[$l * 3 + $k] += $v[$l] * $v[$k] / $d;
            }
          }
        }
        $dx = abs($w->x - $s->x);
        $dy = abs($w->y - $s->y);
        if ($dx <= 0.5 && $dy <= 0.5) {
          $path->curve->vertex[$i] = new Point($w->x + $x0, $w->y + $y0);
          continue;
        }

        $min = $quadform($Q, $s);
        $xmin = $s->x;
        $ymin = $s->y;

        if ($Q->at(0, 0) != 0.0) {
          for ($z = 0; $z < 2; $z++) {
            $w->y = $s->y - 0.5 + $z;
            $w->x = - ($Q->at(0, 1) * $w->y + $Q->at(0, 2)) / $Q->at(0, 0);
            $dx = abs($w->x - $s->x);
            $cand = $quadform($Q, $w);
            if ($dx <= 0.5 && $cand < $min) {
              $min = $cand;
              $xmin = $w->x;
              $ymin = $w->y;
            }
          }
        }

        if ($Q->at(1, 1) != 0.0) {
          for ($z = 0; $z < 2; $z++) {
            $w->x = $s->x - 0.5 + $z;
            $w->y = - ($Q->at(1, 0) * $w->x + $Q->at(1, 2)) / $Q->at(1, 1);
            $dy = abs($w->y - $s->y);
            $cand = $quadform($Q, $w);
            if ($dy <= 0.5 && $cand < $min) {
              $min = $cand;
              $xmin = $w->x;
              $ymin = $w->y;
            }
          }
        }

        for ($l = 0; $l < 2; $l++) {
          for ($k = 0; $k < 2; $k++) {
            $w->x = $s->x - 0.5 + $l;
            $w->y = $s->y - 0.5 + $k;
            $cand = $quadform($Q, $w);
            if ($cand < $min) {
              $min = $cand;
              $xmin = $w->x;
              $ymin = $w->y;
            }
          }
        }

        $path->curve->vertex[$i] = new Point($xmin + $x0, $ymin + $y0);
      }
    };

    //reverse function
    $reverse = function (&$path) {
      $curve = &$path->curve;
      $m = &$curve->n;
      $v = &$curve->vertex;

      for ($i = 0, $j = $m - 1; $i < $j; $i++, $j--) {
        $tmp = $v[$i];
        $v[$i] = $v[$j];
        $v[$j] = $tmp;
      }
    };

    //smooth function
    $smooth = function (&$path) use ($mod, $interval, $ddenom, $dpara, $info) {
      $m = $path->curve->n;
      $curve = &$path->curve;

      for ($i = 0; $i < $m; $i++) {
        $j = $mod($i + 1, $m);
        $k = $mod($i + 2, $m);
        $p4 = $interval(1 / 2.0, $curve->vertex[$k], $curve->vertex[$j]);

        $denom = $ddenom($curve->vertex[$i], $curve->vertex[$k]);
        if ($denom != 0.0) {
          $dd = $dpara($curve->vertex[$i], $curve->vertex[$j], $curve->vertex[$k]) / $denom;
          $dd = abs($dd);
          $alpha = $dd > 1 ? (1 - 1.0 / $dd) : 0;
          $alpha = $alpha / 0.75;
        } else {
          $alpha = 4 / 3.0;
        }
        $curve->alpha0[$j] = $alpha;

        if ($alpha >= $info->alphamax) {
          $curve->tag[$j] = "CORNER";
          $curve->c[3 * $j + 1] = $curve->vertex[$j];
          $curve->c[3 * $j + 2] = $p4;
        } else {
          if ($alpha < 0.55) {
            $alpha = 0.55;
          } else if ($alpha > 1) {
            $alpha = 1;
          }
          $p2 = $interval(0.5 + 0.5 * $alpha, $curve->vertex[$i], $curve->vertex[$j]);
          $p3 = $interval(0.5 + 0.5 * $alpha, $curve->vertex[$k], $curve->vertex[$j]);
          $curve->tag[$j] = "CURVE";
          $curve->c[3 * $j + 0] = $p2;
          $curve->c[3 * $j + 1] = $p3;
          $curve->c[3 * $j + 2] = $p4;
        }
        $curve->alpha[$j] = $alpha;
        $curve->beta[$j] = 0.5;
      }
      $curve->alphacurve = 1;
    };

    //opticurve function
    $optiCurve = function (&$path) use ($mod, $ddist, $sign, $cprod, $dpara, $interval, $tangent, $bezier, $iprod, $iprod1, $info) {
      $opti_penalty = function ($path, $i, $j, $res, $opttolerance, $convc, $areac) use ($mod, $ddist, $sign, $cprod, $dpara, $interval, $tangent, $bezier, $iprod, $iprod1) {
        $m = $path->curve->n;
        $curve = $path->curve;
        $vertex = $curve->vertex;
        if ($i == $j) {
          return 1;
        }

        $k = $i;
        $i1 = $mod($i + 1, $m);
        $k1 = $mod($k + 1, $m);
        $conv = $convc[$k1];
        if ($conv == 0) {
          return 1;
        }
        $d = $ddist($vertex[$i], $vertex[$i1]);
        for ($k = $k1; $k != $j; $k = $k1) {
          $k1 = $mod($k + 1, $m);
          $k2 = $mod($k + 2, $m);
          if ($convc[$k1] != $conv) {
            return 1;
          }
          if (
            $sign($cprod($vertex[$i], $vertex[$i1], $vertex[$k1], $vertex[$k2])) !=
            $conv
          ) {
            return 1;
          }
          if (
            $iprod1($vertex[$i], $vertex[$i1], $vertex[$k1], $vertex[$k2]) <
            $d * $ddist($vertex[$k1], $vertex[$k2]) * -0.999847695156
          ) {
            return 1;
          }
        }

        $p0 = clone $curve->c[$mod($i, $m) * 3 + 2];
        $p1 = clone $vertex[$mod($i + 1, $m)];
        $p2 = clone $vertex[$mod($j, $m)];
        $p3 = clone $curve->c[$mod($j, $m) * 3 + 2];

        $area = $areac[$j] - $areac[$i];
        $area -= $dpara($vertex[0], $curve->c[$i * 3 + 2], $curve->c[$j * 3 + 2]) / 2;
        if ($i >= $j) {
          $area += $areac[$m];
        }

        $A1 = $dpara($p0, $p1, $p2);
        $A2 = $dpara($p0, $p1, $p3);
        $A3 = $dpara($p0, $p2, $p3);

        $A4 = $A1 + $A3 - $A2;

        if ($A2 == $A1) {
          return 1;
        }

        $t = $A3 / ($A3 - $A4);
        $s = $A2 / ($A2 - $A1);
        $A = $A2 * $t / 2.0;

        if ($A == 0.0) {
          return 1;
        }

        $R = $area / $A;
        $alpha = 2 - sqrt(4 - $R / 0.3);

        $res->c[0] = $interval($t * $alpha, $p0, $p1);
        $res->c[1] = $interval($s * $alpha, $p3, $p2);
        $res->alpha = $alpha;
        $res->t = $t;
        $res->s = $s;

        $p1 = clone $res->c[0];
        $p2 = clone $res->c[1];

        $res->pen = 0;

        for ($k = $mod($i + 1, $m); $k != $j; $k = $k1) {
          $k1 = $mod($k + 1, $m);
          $t = $tangent($p0, $p1, $p2, $p3, $vertex[$k], $vertex[$k1]);
          if ($t < -0.5) {
            return 1;
          }
          $pt = $bezier($t, $p0, $p1, $p2, $p3);
          $d = $ddist($vertex[$k], $vertex[$k1]);
          if ($d == 0.0) {
            return 1;
          }
          $d1 = $dpara($vertex[$k], $vertex[$k1], $pt) / $d;
          if (abs($d1) > $opttolerance) {
            return 1;
          }
          if (
            $iprod($vertex[$k], $vertex[$k1], $pt) < 0 ||
            $iprod($vertex[$k1], $vertex[$k], $pt) < 0
          ) {
            return 1;
          }
          $res->pen += $d1 * $d1;
        }

        for ($k = $i; $k != $j; $k = $k1) {
          $k1 = $mod($k + 1, $m);
          $t = $tangent($p0, $p1, $p2, $p3, $curve->c[$k * 3 + 2], $curve->c[$k1 * 3 + 2]);
          if ($t < -0.5) {
            return 1;
          }
          $pt = $bezier($t, $p0, $p1, $p2, $p3);
          $d = $ddist($curve->c[$k * 3 + 2], $curve->c[$k1 * 3 + 2]);
          if ($d == 0.0) {
            return 1;
          }
          $d1 = $dpara($curve->c[$k * 3 + 2], $curve->c[$k1 * 3 + 2], $pt) / $d;
          $d2 = $dpara($curve->c[$k * 3 + 2], $curve->c[$k1 * 3 + 2], $vertex[$k1]) / $d;
          $d2 *= 0.75 * $curve->alpha[$k1];
          if ($d2 < 0) {
            $d1 = -$d1;
            $d2 = -$d2;
          }
          if ($d1 < $d2 - $opttolerance) {
            return 1;
          }
          if ($d1 < $d2) {
            $res->pen += ($d1 - $d2) * ($d1 - $d2);
          }
        }

        return 0;
      };

      $curve = $path->curve;
      $m = $curve->n;
      $vert = $curve->vertex;
      $pt = array_fill(0, $m + 1, NULL);
      $pen = array_fill(0, $m + 1, NULL);
      $len = array_fill(0, $m + 1, NULL);
      $opt = array_fill(0, $m + 1, NULL);
      $o = new Opti();

      $convc = array_fill(0, $m, NULL);
      $areac = array_fill(0, $m + 1, NULL);

      for ($i = 0; $i < $m; $i++) {
        if ($curve->tag[$i] == "CURVE") {
          $convc[$i] = $sign($dpara($vert[$mod($i - 1, $m)], $vert[$i], $vert[$mod($i + 1, $m)]));
        } else {
          $convc[$i] = 0;
        }
      }

      $area = 0.0;
      $areac[0] = 0.0;
      $p0 = $curve->vertex[0];
      for ($i = 0; $i < $m; $i++) {
        $i1 = $mod($i + 1, $m);
        if ($curve->tag[$i1] == "CURVE") {
          $alpha = $curve->alpha[$i1];
          $area += 0.3 * $alpha * (4 - $alpha) *
            $dpara($curve->c[$i * 3 + 2], $vert[$i1], $curve->c[$i1 * 3 + 2]) / 2;
          $area += $dpara($p0, $curve->c[$i * 3 + 2], $curve->c[$i1 * 3 + 2]) / 2;
        }
        $areac[$i + 1] = $area;
      }

      $pt[0] = -1;
      $pen[0] = 0;
      $len[0] = 0;


      for ($j = 1; $j <= $m; $j++) {
        $pt[$j] = $j - 1;
        $pen[$j] = $pen[$j - 1];
        $len[$j] = $len[$j - 1] + 1;

        for ($i = $j - 2; $i >= 0; $i--) {
          $r = $opti_penalty(
            $path,
            $i,
            $mod($j, $m),
            $o,
            $info->opttolerance,
            $convc,
            $areac
          );
          if ($r) {
            break;
          }
          if (
            $len[$j] > $len[$i] + 1 ||
            ($len[$j] == $len[$i] + 1 && $pen[$j] > $pen[$i] + $o->pen)
          ) {
            $pt[$j] = $i;
            $pen[$j] = $pen[$i] + $o->pen;
            $len[$j] = $len[$i] + 1;
            $opt[$j] = $o;
            $o = new Opti();
          }
        }
      }
      $om = $len[$m];
      $ocurve = new Curve($om);
      $s = array_fill(0, $om, NULL);
      $t = array_fill(0, $om, NULL);

      $j = $m;
      for ($i = $om - 1; $i >= 0; $i--) {
        if ($pt[$j] == $j - 1) {
          $ocurve->tag[$i]     = $curve->tag[$mod($j, $m)];
          $ocurve->c[$i * 3 + 0]    = $curve->c[$mod($j, $m) * 3 + 0];
          $ocurve->c[$i * 3 + 1]    = $curve->c[$mod($j, $m) * 3 + 1];
          $ocurve->c[$i * 3 + 2]    = $curve->c[$mod($j, $m) * 3 + 2];
          $ocurve->vertex[$i]  = $curve->vertex[$mod($j, $m)];
          $ocurve->alpha[$i]   = $curve->alpha[$mod($j, $m)];
          $ocurve->alpha0[$i]  = $curve->alpha0[$mod($j, $m)];
          $ocurve->beta[$i]    = $curve->beta[$mod($j, $m)];
          $s[$i] = $t[$i] = 1.0;
        } else {
          $ocurve->tag[$i] = "CURVE";
          $ocurve->c[$i * 3 + 0] = $opt[$j]->c[0];
          $ocurve->c[$i * 3 + 1] = $opt[$j]->c[1];
          $ocurve->c[$i * 3 + 2] = $curve->c[$mod($j, $m) * 3 + 2];
          $ocurve->vertex[$i] = $interval(
            $opt[$j]->s,
            $curve->c[$mod($j, $m) * 3 + 2],
            $vert[$mod($j, $m)]
          );
          $ocurve->alpha[$i] = $opt[$j]->alpha;
          $ocurve->alpha0[$i] = $opt[$j]->alpha;
          $s[$i] = $opt[$j]->s;
          $t[$i] = $opt[$j]->t;
        }
        $j = $pt[$j];
      }

      for ($i = 0; $i < $om; $i++) {
        $i1 = $mod($i + 1, $om);
        if (($s[$i] + $t[$i1]) != 0) {
          $ocurve->beta[$i] = $s[$i] / ($s[$i] + $t[$i1]);
        } else {
          $ocurve->beta[$i] = INF; // TODO Hack para evitar división por 0
        }
      }
      $ocurve->alphacurve = 1;
      $path->curve = $ocurve;
    };
    // var_dump("pathlist:",$this->pathlist);
    for ($i = 0; $i < count($this->pathlist); $i++) {
      $path = &$this->pathlist[$i];
      $calcSums($path);
      $calcLon($path);
      $bestPolygon($path);
      $adjustVertices($path);

      if ($path->sign === "-") {
        $reverse($path);
      }

      $smooth($path);

      if ($info->optcurve) {
        $optiCurve($path);
      }
    }
  }

  public function process()
  {
    $this->bmToPathlist();
    $this->processPath();
  }

  public function clear()
  {
    $this->bm = null;
    $this->pathlist = array();
  }

  public function getSVG($size, $opt_type = '')
  {
    $bm = &$this->bm;
    $pathlist = &$this->pathlist;
    $path = function ($curve) use ($size) {

      $bezier = function ($i) use ($curve, $size) {
        $b = 'C ' . number_format($curve->c[$i * 3 + 0]->x * $size, 3, ".", "") . ' ' .
          number_format($curve->c[$i * 3 + 0]->y * $size, 3, ".", "") . ',';
        $b .= number_format($curve->c[$i * 3 + 1]->x * $size, 3, ".", "") . ' ' .
          number_format($curve->c[$i * 3 + 1]->y * $size, 3, ".", "") . ',';
        $b .= number_format($curve->c[$i * 3 + 2]->x * $size, 3, ".", "") . ' ' .
          number_format($curve->c[$i * 3 + 2]->y * $size, 3, ".", "") . ' ';
        return $b;
      };

      $segment = function ($i) use ($curve, $size) {
        $s = 'L ' . number_format($curve->c[$i * 3 + 1]->x * $size, 3, ".", "") . ' ' .
          number_format($curve->c[$i * 3 + 1]->y * $size, 3, ".", "") . ' ';
        $s .= number_format($curve->c[$i * 3 + 2]->x * $size, 3, ".", "") . ' ' .
          number_format($curve->c[$i * 3 + 2]->y * $size, 3, ".", "") . ' ';
        return $s;
      };

      $n = $curve->n;
      $p = 'M' . number_format($curve->c[($n - 1) * 3 + 2]->x * $size, 3, ".", "") .
        ' ' . number_format($curve->c[($n - 1) * 3 + 2]->y * $size, 3, ".", "") . ' ';

      for ($i = 0; $i < $n; $i++) {
        if ($curve->tag[$i] === "CURVE") {
          $p .= $bezier($i);
        } else if ($curve->tag[$i] === "CORNER") {
          $p .= $segment($i);
        }
      }
      //p +=
      return $p;
    };

    $w = $bm->w * $size / 4;
    $h = $bm->h * $size / 5;
    $len = count($pathlist);
    $svg = '<canvas id="myCanvas" width="' . $w . '" height="' . $h . '" style="position:absolute;z-index: 10;"></canvas>';
    $svg .= '<svg id="demo" version="1.1" width="' . $w . '" height="' . $h .
      '" xmlns="http://www.w3.org/2000/svg" style="position:relative;">';
    $svg .= '<g class="theWords" stroke-linejoin="round">';
    $svg .= '<g id="handwriting" transform="translate(' . $w/5 . ', '. $h/6 .' ) scale(0.1500000, 0.1500000)">';
    // $svg .= '<path d="';

    // for ($i = 0; $i < $len; $i++) {
    //   $c = $pathlist[$i]->curve;
    //   $svg .= $path($c);
    // }

    // $strokec = "none";
    // $fillc = "black";
    // $fillrule = ' fill-rule="evenodd"';
    // $svg .= '" stroke="' . $strokec . '" fill="' . $fillc . '"' . $fillrule . '/>';

    $svg .= '';

    for ($i = 0; $i < 1; $i++) {
      $svg .= '<path d="';

      for ($j = 0; $j < $len; $j++) {
        $c = $pathlist[$j]->curve;
        $svg .= $path($c);
      }

      $strokec = "black";
      $fillc = "none";
      $fillrule = ' fill-rule="evenodd"';
      $svg .= '" stroke="' . $strokec . '" fill="' . $fillc . '"' . $fillrule . '/>';
    }


    // $svg .= '</g></g></svg>';
    $svg .= '</g></g>
      <g id="hand">
        <rect id="groupSizer" width="140" height="160" fill="none"/>
        <path d="M55.25,55.9,48.63,82.66s-27.58,40.19-26.86,16C23.31,47.27,55.25,55.9,55.25,55.9Z"
            fill="#f49f58" />
        <path
            d="M77.45,30.06C75.95,29.74,27.15,45,24.08,46.12S13.72,48,11.42,53.79s0,16.5,0,21.1-1.15,33.38-.77,37.6c.24,2.7,3.45,5.37,6.52,5.75s8.06-4.6,9.21-7.67,6.52-40.67,7.29-43.35,13-1.15,15.73-1.15,26.47,23.4,26.47,26.86-14.19,19.57-16.11,19.57-29.63,6.77-31.46,8.06c-3.84,2.69-6.93,9.81-5.75,16.88.77,4.6,6.14,6.14,10.36,5.76,3.62-.33,24.17-1.53,30.69-1.92,4.28-.25,12.66-3.84,15-5.37S97,121.7,98.12,119.78s15.08-12.56,17.26-16.69c3.45-6.52,14.39-48.34,13.24-50.07C123.39,45.17,83.76,31.37,77.45,30.06Z"
            fill="#ffc585" />
        <path
            d="M119.82,46.59c-7.44-4-18-8.3-26.85-11.51h0C81.87,48.19,69.38,60,56.92,71.77h0c7.89,7.16,19,18.8,19,21.15,0,1.58-3,5.82-6.39,9.95h0c1.15,0,8.42,8.43,21.74-4.39C111.74,78.73,119,66.42,120.74,56.26A17,17,0,0,0,119.82,46.59Z"
            fill="#ffc585" opacity="0.4" style="mix-blend-mode: multiply" />
        <path
            d="M14.1,70.29c-2.3,7.29-9.8,45.47-10,49.11-.21,4.32,1.83,7.15,5.75,6.91,3.39-.21,8.58-2.94,10.36-6.14,3.34-6,4.19-5.22,11.13-50.26Z"
            fill="#ffc585" />
        <path
            d="M14.1,70.29c-2.3,7.29-9.8,45.47-10,49.11-.21,4.32,1.83,7.15,5.75,6.91,3.39-.21,8.58-2.94,10.36-6.14,3.34-6,4.19-5.22,11.13-50.26Z"
            fill="#ffc585" opacity="0.4" style="mix-blend-mode: multiply" />
        <g>
            <path
                d="M115.22,36.89s-4.3,37.94-5.1,39.36S83.83,106.38,81.9,107.18,69.43,109.49,71.37,105s7.33-2.93,8.17-3.66S101.42,75.61,102,74.43s.74-30,.74-30Z"
                fill="#363f4f" />
            <path
                d="M113.07,39.08s-3.87,36-4.66,37.41S83.11,106.2,81.18,107s-11.75,2.49-9.81-2,7.33-2.93,8.17-3.66S101.42,75.61,102,74.43s.74-30,.74-30Z"
                fill="#4b5668" />
            <path
                d="M111.25,55.36c1-8.48,1.81-16.28,1.81-16.28l-10.31,5.4s-.08,15.28-.35,24C103.3,67.19,108.68,59.83,111.25,55.36Z"
                fill="#4b5668" opacity="0.4" style="mix-blend-mode: multiply" />
            <path
                d="M71.55,107.11c2.19-3.36,7.43-5.35,7.69-5.61-1.41.38-6.08-.56-7.86,3.53A1.73,1.73,0,0,0,71.55,107.11Z"
                fill="#4b5668" opacity="0.4" style="mix-blend-mode: multiply" />
            <path d="M33.23,86.72c10.08,3.3,24,15.06,29.31,25.8L58,117.38c-10.6-4-25.41-17.87-28-25.83Z"
                fill="#edbd15" />
            <path
                d="M46.16,94c-2.09,3-4.54,5.8-6.79,8.77-.24.32-.47.65-.7,1,5.75,5.83,13.22,11.38,19.3,13.65l4.57-4.85C59.32,106,52.93,99.13,46.16,94Z"
                fill="#edbd15" style="mix-blend-mode: multiply" />
            <path d="M3.79,149.09s-3.57,7.55-2.42,8.58c1.52,1.36,7.71-4.53,7.71-4.53Z" fill="#c6d0d5" />
            <path
                d="M7.95,150.39c-1.11,2.4-3.21,5.47-5.9,6.3a3.13,3.13,0,0,1-.73.13.78.78,0,0,0,.21.51c1.52,1.36,7.71-4.53,7.71-4.53Z"
                fill="#c7d1d6" style="mix-blend-mode: multiply" />
            <path
                d="M1.95,149.82c3.16-8.14,17.61-40.14,31.5-61.3,8.37-12.75,53.86-84,78.16-64.8,21.59,17-31.06,69.86-52.81,90.88C42,130.87,11.55,153.39,9.34,154.72S1.34,151.39,1.95,149.82Z"
                fill="#4b5668" />
            <path
                d="M49,113.63c-6.42,6.38-20.09,20-29.15,28.48C11,150.42,6.2,152.81,4.59,153.36c1.65,1.19,3.64,2,4.75,1.36,2.22-1.33,32.63-23.85,49.45-40.11C78,96.07,121.16,52.8,116.31,31.15c0,2.37-7.76,3.86-12,13.2C95.55,63.76,63.76,99,49,113.63Z"
                fill="#4b5668" opacity="0.4" style="mix-blend-mode: multiply" />
            <path
                d="M92.86,47.91c7.9,6.12,17.67,6.77,19.08,3.07,5.75-11.47,7-21.45-.34-27.26-8-6.33-18.36-2.79-28.91,5.24C80.46,32.85,84.33,41.31,92.86,47.91Z"
                fill="#69778b" />
            <path
                d="M83.79,28.14C94,20.69,103.86,17.61,111.6,23.72c7.17,5.66,6.15,15.26.79,26.34C106.53,58.36,80.56,40.72,83.79,28.14Z"
                fill="#edbd15" />
            <path
                d="M116.31,31.11c-.65,10.45-11.22,20.6-22,15.52,6.91,5.15,15.12,7.66,18.12,3.43C115.86,42.91,117.5,36.37,116.31,31.11Z"
                fill="#edbd15" style="mix-blend-mode: multiply" />
            <path
                d="M10.5,130.62c-4.17,8.76-7.28,15.93-8.55,19.21-.61,1.56,5.17,6.22,7.39,4.9,1-.59,7.45-5.31,15.83-11.79C20.75,141.85,11.74,135.18,10.5,130.62Z"
                fill="#edbd15" />
            <path d="M10.5,130.62c-1.89,4.89,10,12.92,14.67,12.31C20.75,141.85,11.74,135.18,10.5,130.62Z"
                fill="#feee28" />
            <path
                d="M2.51,151.41a8,8,0,0,0,.9,1c2.45-3.69,7.82-11.23,11.22-16a19.94,19.94,0,0,1-3-3.41C8.87,137.68,5,146,2.51,151.41Z"
                fill="#edbd15" style="mix-blend-mode: screen" />
            <path
                d="M20.4,140.73c-4.19,5-9,9.31-13.71,13.83a3.09,3.09,0,0,0,2.64.16c1-.59,7.45-5.31,15.83-11.79A18.38,18.38,0,0,1,20.4,140.73Z"
                fill="#edbd15" style="mix-blend-mode: multiply" />
            <path d="M33.23,86.72c4.3,8.5,19.27,22.52,29.31,25.8L58,117.38c-10.6-4-25.41-17.87-28-25.83Z"
                fill="#feee28" />
            <path d="M33.23,86.72c3.3,10,19.4,24.28,29.31,25.8L58,117.38c-10.6-4-25.41-17.87-28-25.83Z"
                fill="#edbd15" />
            <path
                d="M53.09,107.47a38.21,38.21,0,0,0,9.45,5.06L58,117.38a43,43,0,0,1-9.29-5.15C50.14,110.87,51.15,109.6,53.09,107.47Z"
                fill="#edbd15" style="mix-blend-mode: multiply" />
            <path
                d="M39,94.81c-.92-1-1.78-2.09-2.55-3.1-1.26,1.68-2.45,3.41-3.59,5.18.6.86,1.28,1.73,2,2.62A61.6,61.6,0,0,1,39,94.81Z"
                fill="#edbd15" style="mix-blend-mode: screen" />
            <path d="M3.45,153c1,.55-1.26,3.33-1.42,2.73S2.63,152.54,3.45,153Z" fill="#c7d1d6" opacity="0.7"
                style="mix-blend-mode: screen" />
            <path d="M107.61,75.33c.49-1.63-25.92,28.23-25.4,28.92S106.8,78,107.61,75.33Z" fill="#4b5668"
                opacity="0.7" style="mix-blend-mode: screen" />
            <path
                d="M101.93,22c-3.44-.3-9,3-8.18,4.61,2.2,4.21,12.06,2,12.88,0C107.76,23.75,103.74,22.19,101.93,22Z"
                fill="#edbd15" opacity="0.4" style="mix-blend-mode: screen" />
            <path
                d="M81,41.38c-5.59.22-35.49,37.16-38.3,46.24,3.74-1.84,11.19-13.24,18.86-22.54C70.83,53.89,80.15,44.46,81,41.38Z"
                fill="#4b5668" opacity="0.4" style="mix-blend-mode: screen" />
            <path d="M32.75,100.8c-3.63,2.63-15.13,19.8-17.94,28.88C18.78,125.16,31.85,103.88,32.75,100.8Z"
                fill="#4b5668" opacity="0.4" style="mix-blend-mode: screen" />
        </g>
        <path
            d="M43.35,65.72C43.74,64.29,71.25,36,73.6,31.06c-8.52,2.7-47.07,14.14-49.52,15.06C21,47.27,13.72,48,11.42,53.79s0,16.5,0,21.1-1.15,33.38-.77,37.6c.24,2.7,3.45,5.37,6.52,5.75s8.06-4.6,9.21-7.67,6.52-40.67,7.29-43.35C34.14,65.55,39,65.51,43.35,65.72Z"
            fill="#ffc585" />
        <path
            d="M69.51,111.93a10.24,10.24,0,0,1-4.45-3.18c-2.5,2.3-4.66,3.74-5.3,3.74-1.92,0-29.63,6.77-31.46,8.06-3.84,2.69-6.93,9.81-5.75,16.88.77,4.6,6.14,6.14,10.36,5.76,3.62-.33,24.17-1.53,30.69-1.92,4.28-.25,12.66-3.84,15-5.37S97,121.7,98.12,119.78C98.3,119.48,73.84,113.57,69.51,111.93Z"
            fill="#ffc585" />
        <path
            d="M27.53,124.77c2.69-2.69,15-2.3,15.35-.38s.38,10-.38,12.28-14.19,5-15.73,2.69C23.39,134.28,25.1,127.19,27.53,124.77Z"
            fill="#ffd5b8" />
        <path
            d="M19,116a81.46,81.46,0,0,0-.29-14.29c-.34-1.68-6.65-1.76-8-2.1-.15,6-.23,11.23-.08,12.84C10.89,115.19,17.27,117.77,19,116Z"
            fill="#ffd5b8" />
        <path
            d="M117,91.87c-8.53,11-35.2,38.27-45.36,42.58-9.4,4-40.57,9.5-49.11,3,.77,4.6,6.14,6.14,10.36,5.76,3.62-.33,24.17-1.53,30.69-1.92,4.28-.25,12.66-3.84,15-5.37S97,121.7,98.12,119.78s15.08-12.56,17.26-16.69c1.12-2.12,3-7.94,5.05-14.91C119.34,89.44,118,90.54,117,91.87Z"
            fill="#ffc585" opacity="0.4" style="mix-blend-mode: multiply" />
        <path
            d="M26.38,110.57c1.15-3.07,6.52-40.67,7.29-43.35.54-1.89,6.78-1.69,11.37-1.41.76,0-11.61-4.73-14-1.47-3.27,4.54-3.16,35.87-12,50.07a5.88,5.88,0,0,1-5.31,2.53,8.13,8.13,0,0,0,3.39,1.31C20.24,118.63,25.23,113.64,26.38,110.57Z"
            fill="#ffc585" opacity="0.4" style="mix-blend-mode: multiply" />
      </g>
    </svg>';
    return $svg;
  }
}
