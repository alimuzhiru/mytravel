<?php
namespace Modules\Tour\Controllers;

use App\Notifications\AdminChannelServices;
use Modules\Booking\Events\BookingUpdatedEvent;
use Modules\Core\Events\CreatedServicesEvent;
use Modules\FrontendController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Location\Models\LocationCategory;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourCategory;
use Modules\Tour\Models\TourTranslation;
use Modules\Location\Models\Location;
use Modules\Core\Models\Attributes;
use Modules\Tour\Models\TourTerm;
use Modules\Booking\Models\Booking;

class ManageTourController extends FrontendController
{
    protected $tourClass;
    protected $tourTranslationClass;
    protected $tourCategoryClass;
    protected $tourTermClass;
    protected $attributesClass;
    protected $locationClass;
    protected $bookingClass;
    /**
     * @var string
     */
    private $locationCategoryClass;

    public function __construct()
    {
        $this->tourClass = Tour::class;
        $this->tourTranslationClass = TourTranslation::class;
        $this->tourCategoryClass = TourCategory::class;
        $this->tourTermClass = TourTerm::class;
        $this->attributesClass = Attributes::class;
        $this->locationClass = Location::class;
        $this->locationCategoryClass = LocationCategory::class;
        $this->bookingClass = Booking::class;
        parent::__construct();
    }

    public function callAction($method, $parameters)
    {
        if (setting_item('tour_disable')) {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }

    public function manageTour(Request $request)
    {
        $this->checkPermission('tour_view');
        $user_id = Auth::id();
        $list_tour = $this->tourClass::where("create_user", $user_id)->orderBy('id', 'desc');
        $data = [
            'rows'        => $list_tour->paginate(5),
            'breadcrumbs' => [
                [
                    'name' => __('Manage Tours'),
                    'url'  => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'  => __("Manage Tours"),
        ];
        return view('Tour::frontend.manageTour.index', $data);
    }

    public function recovery(Request $request)
    {
        $this->checkPermission('tour_view');
        $user_id = Auth::id();
        $list_tour = $this->tourClass::onlyTrashed()->where("create_user", $user_id)->orderBy('id', 'desc');
        $data = [
            'rows'        => $list_tour->paginate(5),
            'recovery'           => 1,
            'breadcrumbs' => [
                [
                    'name' => __('Manage Tours'),
                    'url'  => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('Recovery'),
                    'class' => 'active'
                ],
            ],
            'page_title'  => __("Recovery Tours"),
        ];
        return view('Tour::frontend.manageTour.index', $data);
    }

    public function restore($id)
    {
        $this->checkPermission('tour_delete');
        $user_id = Auth::id();
        $query = $this->tourClass::onlyTrashed()->where("create_user", $user_id)->where("id", $id)->first();
        if(!empty($query)){
            $query->restore();
        }
        return redirect(route('tour.vendor.recovery'))->with('success', __('Restore tour success!'));
    }

    public function createTour(Request $request)
    {
        $this->checkPermission('tour_create');
        $row = new $this->tourClass();
        $data = [
            'row'           => $row,
            'translation'   => new $this->tourTranslationClass(),
            'tour_category' => $this->tourCategoryClass::get()->toTree(),
            'tour_location' => $this->locationClass::where("status", "publish")->get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'tour')->get(),
            'location_category'=>$this->locationCategoryClass::where("status", "publish")->get(),

            'breadcrumbs'   => [
                [
                    'name' => __('Manage Tours'),
                    'url'  => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('Create'),
                    'class' => 'active'
                ],
            ],
            'page_title'    => __("Create Tours"),
        ];
        return view('Tour::frontend.manageTour.detail', $data);
    }

    public function editTour(Request $request, $id)
    {
        $this->checkPermission('tour_update');
        $user_id = Auth::id();
        $row = $this->tourClass::where("create_user", $user_id);
        $row = $row->find($id);
        if (empty($row)) {
            return redirect(route('tour.vendor.index'))->with('warning', __('Tour not found!'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        $data = [
            'translation'    => $translation,
            'row'            => $row,
            'tour_category'  => $this->tourCategoryClass::where("status", "publish")->get()->toTree(),
            'tour_location'  => $this->locationClass::where("status", "publish")->get()->toTree(),
            'location_category'=>$this->locationCategoryClass::where("status", "publish")->get(),
            'attributes'     => $this->attributesClass::where('service', 'tour')->get(),
            "selected_terms" => $row->tour_term->pluck('term_id'),
            'breadcrumbs'    => [
                [
                    'name' => __('Manage Tours'),
                    'url'  => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('Edit'),
                    'class' => 'active'
                ],
            ],
            'page_title'     => __("Edit Tours"),
        ];
        return view('Tour::frontend.manageTour.detail', $data);
    }

    public function store(Request $request, $id)
    {
        if ($id > 0) {
            $this->checkPermission('tour_update');
            $row = $this->tourClass::find($id);
            if (empty($row)) {
                return redirect(route('tour.vendor.edit', ['id' => $row->id]));
            }
            if ($row->create_user != Auth::id() and !$this->hasPermission('tour_manage_others')) {
                return redirect(route('tour.vendor.edit', ['id' => $row->id]));
            }
        } else {
            $this->checkPermission('tour_create');
            $row = new $this->tourClass();
            $row->status = "publish";
            if (setting_item("tour_vendor_create_service_must_approved_by_admin", 0)) {
                $row->status = "pending";
            }
        }
        $row->fillByAttr([
            'title',
            'content',
            'image_id',
            'banner_image_id',
            'short_desc',
            'category_id',
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',
            'gallery',
            'video',
            'default_state',
            'price',
            'sale_price',
            'duration',
            'max_people',
            'min_people',
            'faqs',
            'include',
            'exclude',
            'itinerary',
            'enable_service_fee',
            'service_fee',
            'surrounding',
            'min_day_before_booking',

        ], $request->input());
        $row->ical_import_url = $request->ical_import_url;
        $res = $row->saveOriginOrTranslation($request->input('lang'), true);
        if ($res) {
            if (!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
                $row->saveMeta($request);
            }
            if ($id > 0) {
                return back()->with('success', __('Tour updated'));
            } else {
                event(new CreatedServicesEvent($row));
                return redirect(route('tour.vendor.edit', ['id' => $row->id]))->with('success', __('Tour created'));
            }
        }
    }

    public function saveTerms($row, $request)
    {
        if (empty($request->input('terms'))) {
            $this->tourTermClass::where('tour_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->tourTermClass::firstOrCreate([
                    'term_id' => $term_id,
                    'tour_id' => $row->id
                ]);
            }
            $this->tourTermClass::where('tour_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
        }
    }

    public function deleteTour($id)
    {
        $this->checkPermission('tour_delete');
        $user_id = Auth::id();
        if(\request()->query('permanently_delete')){
            $query = $this->tourClass::where("create_user", $user_id)->where("id", $id)->withTrashed()->first();
            if (!empty($query)) {
                $query->forceDelete();
            }
        }else {
            $query = $this->tourClass::where("create_user", $user_id)->where("id", $id)->first();
            if (!empty($query)) {
                $query->delete();
            }
        }
        return redirect(route('tour.vendor.index'))->with('success', __('Delete tour success!'));
    }

    public function bulkEditTour($id, Request $request)
    {
        $this->checkPermission('tour_update');
        $action = $request->input('action');
        $user_id = Auth::id();
        $query = $this->tourClass::where("create_user", $user_id)->where("id", $id)->first();
        if (empty($id)) {
            return redirect()->back()->with('error', __('No item!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }
        if (empty($query)) {
            return redirect()->back()->with('error', __('Not Found'));
        }
        switch ($action) {
            case "make-hide":
                $query->status = "draft";
                break;
            case "make-publish":
                $query->status = "publish";
                break;
        }
        $query->save();
        return redirect()->back()->with('success', __('Update success!'));
    }

    public function bookingReportBulkEdit($booking_id, Request $request)
    {
        $status = $request->input('status');
        if (!empty(setting_item("tour_allow_vendor_can_change_their_booking_status")) and !empty($status) and !empty($booking_id)) {
            $query = $this->bookingClass::where("id", $booking_id);
            $query->where("vendor_id", Auth::id());
            $item = $query->first();
            if (!empty($item)) {
                $item->status = $status;
                $item->save();

                if($status == Booking::CANCELLED) $item->tryRefundToWallet();

                event(new BookingUpdatedEvent($item));
                return redirect()->back()->with('success', __('Update success'));
            }
            return redirect()->back()->with('error', __('Booking not found!'));
        }
        return redirect()->back()->with('error', __('Update fail!'));
    }

    public function cloneTour(Request $request, $id)
    {
        $this->checkPermission('tour_update');
        $user_id = Auth::id();
        $row = $this->tourClass::where("create_user", $user_id);
        $row = $row->find($id);
        if (empty($row)) {
            return redirect(route('tour.vendor.index'))->with('warning', __('Tour not found!'));
        };
        try {
            $clone = $row->replicate();
            $clone->status = 'draft';
            $clone->push();
            if (!empty($row->tour_term)) {
                foreach ($row->tour_term as $term) {
                    $e = $term->replicate();
                    if ($e->push()) {
                        $clone->tour_term()->save($e);
                    }
                }
            }
            if (!empty($row->meta)) {
                $e = $row->meta->replicate();
                if ($e->push()) {
                    $clone->meta()->save($e);
                }
            }
            if (!empty($row->translations)) {
                foreach ($row->translations as $translation) {
                    $e = $translation->replicate();
                    $e->origin_id = $clone->id;
                    if ($e->push()) {
                        $clone->translations()->save($e);
                    }
                }
            }
            return redirect()->back()->with('success', __('Tour clone was successful'));
        } catch (\Exception $exception) {
            $clone->delete();
            return redirect()->back()->with('warning', __($exception->getMessage()));
        }
    }
}
